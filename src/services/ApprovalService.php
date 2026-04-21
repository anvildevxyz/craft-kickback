<?php

declare(strict_types=1);

namespace anvildev\craftkickback\services;

use anvildev\craftkickback\exceptions\ApprovalAlreadyResolvedException;
use anvildev\craftkickback\exceptions\ApprovalNotFoundException;
use anvildev\craftkickback\exceptions\ApprovalTargetMissingException;
use anvildev\craftkickback\exceptions\SelfVerificationException;
use anvildev\craftkickback\helpers\DateHelper;
use anvildev\craftkickback\KickBack;
use anvildev\craftkickback\records\ApprovalRecord;
use anvildev\craftkickback\services\approvals\ApprovalTargetInterface;
use Craft;
use craft\base\Component;
use yii\base\Event;
use yii\db\IntegrityException;

/**
 * Handles the approval lifecycle for polymorphic targets. Pure validation
 * helpers (checkSelfVerify, checkResolvable, requireNonEmptyRejectionNote) are
 * static so they can be unit tested without a DB bootstrap.
 */
class ApprovalService extends Component
{
    public const EVENT_AFTER_REQUEST = 'afterRequest';
    public const EVENT_AFTER_APPROVE = 'afterApprove';
    public const EVENT_AFTER_REJECT = 'afterReject';

    /** @var array<string, class-string<ApprovalTargetInterface>> */
    private array $targets = [];

    /**
     * Per-request cache of instantiated handlers so handlers can memoise
     * their own lookups across a full queue render.
     *
     * @var array<string, ApprovalTargetInterface>
     */
    private array $targetInstances = [];

    /**
     * Per-request cache: targetType → (targetId → ApprovalRecord). Warmed in
     * one query per targetType on first getFor() call to avoid N+1 lookups.
     *
     * @var array<string, array<int, ApprovalRecord|null>>
     */
    private array $approvalCache = [];

    /**
     * @param class-string<ApprovalTargetInterface> $handlerClass
     */
    public function registerTarget(string $targetType, string $handlerClass): void
    {
        $this->targets[$targetType] = $handlerClass;
    }

    public function getTargetHandler(string $targetType): ApprovalTargetInterface
    {
        $class = $this->targets[$targetType]
            ?? throw new \InvalidArgumentException("Unregistered approval target type: {$targetType}");

        return $this->targetInstances[$targetType] ??= new $class();
    }

    /**
     * Four-eyes rule: a resolver cannot approve/reject a target they created.
     * Admins are not exempt.
     *
     * @throws SelfVerificationException
     */
    public static function checkSelfVerify(int $resolverId, ?int $creatorId): void
    {
        if ($creatorId !== null && $resolverId === $creatorId) {
            throw new SelfVerificationException();
        }
    }

    /**
     * State-machine guard: approve/reject only operate on PENDING rows.
     *
     * @throws ApprovalAlreadyResolvedException
     */
    public static function checkResolvable(int $approvalId, string $currentStatus): void
    {
        if ($currentStatus !== ApprovalRecord::STATUS_PENDING) {
            throw ApprovalAlreadyResolvedException::forApproval($approvalId, $currentStatus);
        }
    }

    /**
     * @throws \InvalidArgumentException on null, empty, or whitespace-only input.
     */
    public static function requireNonEmptyRejectionNote(?string $note): string
    {
        if ($note === null) {
            throw new \InvalidArgumentException('Rejection note is required.');
        }
        if (($trimmed = trim($note)) === '') {
            throw new \InvalidArgumentException('Rejection note cannot be empty.');
        }

        return $trimmed;
    }

    /**
     * Idempotent approval request. Concurrency handled via unique index + IntegrityException recovery.
     */
    public function request(string $targetType, int $targetId, ?int $requestedUserId = null): ApprovalRecord
    {
        $handler = $this->getTargetHandler($targetType);
        $key = ['targetType' => $targetType, 'targetId' => $targetId];

        if ($existing = ApprovalRecord::findOne($key)) {
            return $existing;
        }

        $approval = new ApprovalRecord();
        $approval->targetType = $targetType;
        $approval->targetId = $targetId;
        $approval->status = ApprovalRecord::STATUS_PENDING;
        $approval->requestedUserId = $requestedUserId;

        try {
            $approval->save(false);
        } catch (IntegrityException) {
            return ApprovalRecord::findOne($key)
                ?? throw new \RuntimeException("Unique-index race on {$targetType}#{$targetId} but no winning row found");
        }

        $this->invalidateCache($targetType, $targetId);
        $this->maybeAnnounce($approval, $handler);
        $this->trigger(self::EVENT_AFTER_REQUEST, new Event());
        return $approval;
    }

    public function getFor(string $targetType, int $targetId): ?ApprovalRecord
    {
        $this->warmCacheFor($targetType);
        return $this->approvalCache[$targetType][$targetId] ?? null;
    }

    private function warmCacheFor(string $targetType): void
    {
        if (isset($this->approvalCache[$targetType])) {
            return;
        }

        $this->approvalCache[$targetType] = [];

        /** @var ApprovalRecord[] $records */
        $records = ApprovalRecord::find()
            ->where(['targetType' => $targetType])
            ->all();

        foreach ($records as $record) {
            $this->approvalCache[$targetType][$record->targetId] = $record;
        }
    }

    private function invalidateCache(string $targetType, int $targetId): void
    {
        unset($this->approvalCache[$targetType][$targetId]);
    }

    /**
     * @throws ApprovalNotFoundException
     * @throws ApprovalAlreadyResolvedException
     * @throws ApprovalTargetMissingException
     * @throws SelfVerificationException
     */
    public function approve(int $approvalId, int $resolverUserId, ?string $note = null): ApprovalRecord
    {
        [$approval] = $this->loadResolvable($approvalId, $resolverUserId);

        $approval->status = ApprovalRecord::STATUS_APPROVED;
        $approval->resolvedUserId = $resolverUserId;
        $approval->resolvedAt = DateHelper::nowString();
        $trimmed = $note !== null ? trim($note) : '';
        $approval->note = $trimmed !== '' ? $trimmed : null;
        $approval->save(false);

        $this->invalidateCache($approval->targetType, $approval->targetId);
        Craft::info("Approval #{$approval->id} approved by user #{$resolverUserId} for {$approval->targetType}#{$approval->targetId}", __METHOD__);
        $this->trigger(self::EVENT_AFTER_APPROVE, new Event());

        return $approval;
    }

    /**
     * Reject a pending approval inside a transaction. Does not touch balances.
     *
     * @throws ApprovalNotFoundException|ApprovalAlreadyResolvedException|ApprovalTargetMissingException|SelfVerificationException|\InvalidArgumentException
     */
    public function reject(int $approvalId, int $resolverUserId, ?string $note): ApprovalRecord
    {
        [$approval, $handler] = $this->loadResolvable($approvalId, $resolverUserId);
        $trimmedNote = self::requireNonEmptyRejectionNote($note);

        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            $approval->status = ApprovalRecord::STATUS_REJECTED;
            $approval->resolvedUserId = $resolverUserId;
            $approval->resolvedAt = DateHelper::nowString();
            $approval->note = $trimmedNote;
            $approval->save(false);
            $handler->onReject($approval->targetId);
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        $this->invalidateCache($approval->targetType, $approval->targetId);
        Craft::warning("Approval #{$approval->id} rejected by user #{$resolverUserId} for {$approval->targetType}#{$approval->targetId}: {$trimmedNote}", __METHOD__);
        $this->trigger(self::EVENT_AFTER_REJECT, new Event());

        return $approval;
    }

    /**
     * Delete any approval row for a target. Called from target elements'
     * afterDelete() since targetId is polymorphic and can't have a FK.
     */
    public function deleteFor(string $targetType, int $targetId): int
    {
        $deleted = ApprovalRecord::deleteAll([
            'targetType' => $targetType,
            'targetId' => $targetId,
        ]);

        $this->invalidateCache($targetType, $targetId);

        return (int)$deleted;
    }

    /**
     * @return array{0: ApprovalRecord, 1: ApprovalTargetInterface}
     */
    private function loadResolvable(int $approvalId, int $resolverUserId): array
    {
        $approval = ApprovalRecord::findOne($approvalId)
            ?? throw ApprovalNotFoundException::forId($approvalId);

        self::checkResolvable($approval->id, $approval->status);

        $handler = $this->getTargetHandler($approval->targetType);
        if (!$handler->exists($approval->targetId)) {
            throw ApprovalTargetMissingException::forTarget($approval->targetType, $approval->targetId);
        }

        self::checkSelfVerify($resolverUserId, $handler->getCreatorUserId($approval->targetId));

        return [$approval, $handler];
    }

    private function maybeAnnounce(ApprovalRecord $approval, ApprovalTargetInterface $handler): void
    {
        if (!KickBack::getInstance()->getSettings()->notifyVerifierOnRequest || $approval->requestedUserId === null) {
            return;
        }

        // Craft's Announcements::push broadcasts to every CP user - per-user
        // targeting requires email. The requestedUserId gate merely ensures a
        // designated verifier exists at all.
        Craft::$app->getAnnouncements()->push(
            Craft::t('kickback', '{label} needs verification', [
                'label' => $handler->getRowLabel($approval->targetId),
            ]),
            Craft::t('kickback', 'A new payout is waiting for your review.'),
            'kickback',
        );
    }
}
