<?php

declare(strict_types=1);

namespace anvildev\craftkickback\services;

use anvildev\craftkickback\elements\AffiliateElement;
use anvildev\craftkickback\elements\PayoutElement;
use anvildev\craftkickback\events\PayoutEvent;
use anvildev\craftkickback\gateways\PayoutResult;
use anvildev\craftkickback\helpers\DateHelper;
use anvildev\craftkickback\KickBack;
use anvildev\craftkickback\records\ApprovalRecord;
use Craft;
use craft\base\Component;
use craft\helpers\DateTimeHelper;

/**
 * Handles payout creation, processing, and batch disbursement to affiliates.
 */
class PayoutService extends Component
{
    public const EVENT_BEFORE_CREATE_PAYOUT = 'beforeCreatePayout';
    public const EVENT_AFTER_CREATE_PAYOUT = 'afterCreatePayout';
    public const EVENT_BEFORE_PROCESS_PAYOUT = 'beforeProcessPayout';
    public const EVENT_AFTER_PROCESS_PAYOUT = 'afterProcessPayout';

    /**
     * Create a payout for an affiliate using their current pending balance.
     */
    public function createPayout(AffiliateElement $affiliate, ?string $notes = null): ?PayoutElement
    {
        $plugin = KickBack::getInstance();
        $settings = $plugin->getSettings();
        $db = Craft::$app->getDb();

        // SELECT ... FOR UPDATE plus the "no active payout" check guards against
        // concurrent callers double-spending the same balance - recordPayout only
        // deducts at completion, so pending rows don't otherwise reserve their amount.
        $transaction = $db->beginTransaction();
        try {
            $currentBalance = $db->createCommand(
                'SELECT [[pendingBalance]] FROM {{%kickback_affiliates}} WHERE [[id]] = :id FOR UPDATE',
                [':id' => $affiliate->id],
            )->queryScalar();

            if ($currentBalance === false || $currentBalance === null
                || ($currentBalance = (float)$currentBalance) < $settings->minimumPayoutAmount
            ) {
                $transaction->rollBack();
                return null;
            }

            $hasActivePayout = (new \yii\db\Query())
                ->from('{{%kickback_payouts}}')
                ->where(['affiliateId' => $affiliate->id])
                ->andWhere(['in', 'status', [PayoutElement::STATUS_PENDING, PayoutElement::STATUS_PROCESSING]])
                ->exists($db);

            if ($hasActivePayout) {
                Craft::info("createPayout skipped for affiliate #{$affiliate->id} - active payout already exists", __METHOD__);
                $transaction->rollBack();
                return null;
            }

            $affiliate->pendingBalance = $currentBalance;

            $payout = new PayoutElement();
            $payout->affiliateId = $affiliate->id;
            $payout->createdByUserId = Craft::$app->getUser()->getIdentity()?->id;
            $payout->amount = $currentBalance;
            $payout->currency = KickBack::getCommerceCurrency();
            $payout->method = $affiliate->payoutMethod;
            $payout->payoutStatus = PayoutElement::STATUS_PENDING;
            $payout->notes = $notes;

            $eventPayload = ['payout' => $payout, 'affiliate' => $affiliate];
            $this->trigger(self::EVENT_BEFORE_CREATE_PAYOUT, $event = new PayoutEvent($eventPayload));

            if (!$event->isValid) {
                $transaction->rollBack();
                return null;
            }

            // Source-match guardrail: CreatePayoutTransactionTest asserts this
            // explicit shape with a regex so refactors can't silently fuse the
            // rollBack/save pair and lose the explicit rollback branch.
            if (!Craft::$app->getElements()->saveElement($payout)) {
                $transaction->rollBack();
                return null;
            }

            if ($settings->requirePayoutVerification) {
                $plugin->approvals->request('payout', (int)$payout->id, $settings->defaultPayoutVerifierId);
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        $this->trigger(self::EVENT_AFTER_CREATE_PAYOUT, new PayoutEvent($eventPayload));

        return $payout;
    }

    /**
     * Mark a payout as completed and update affiliate balances. Uses a
     * status-conditioned UPDATE so concurrent callers can't double-deduct.
     */
    public function completePayout(PayoutElement $payout, ?string $transactionId = null): bool
    {
        if (!in_array($payout->payoutStatus, [PayoutElement::STATUS_PENDING, PayoutElement::STATUS_PROCESSING], true)) {
            return false;
        }

        $affiliate = KickBack::getInstance()->affiliates->getAffiliateById($payout->affiliateId);
        if ($affiliate === null) {
            return false;
        }

        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            $affectedRows = Craft::$app->getDb()->createCommand()
                ->update(
                    '{{%kickback_payouts}}',
                    [
                        'status' => PayoutElement::STATUS_COMPLETED,
                        'transactionId' => $transactionId,
                        'processedAt' => DateHelper::nowString(),
                    ],
                    [
                        'and',
                        ['id' => $payout->id],
                        ['in', 'status', [PayoutElement::STATUS_PENDING, PayoutElement::STATUS_PROCESSING]],
                    ],
                )
                ->execute();

            if ($affectedRows === 0) {
                $transaction->rollBack();
                Craft::info("Payout #{$payout->id} completePayout lost the status race - already resolved", __METHOD__);
                return false;
            }

            $payout->payoutStatus = PayoutElement::STATUS_COMPLETED;
            $payout->transactionId = $transactionId;
            $payout->processedAt = DateTimeHelper::currentUTCDateTime();
            Craft::$app->getElements()->saveElement($payout, false);

            KickBack::getInstance()->affiliates->recordPayout($affiliate, (float)$payout->amount);
            KickBack::getInstance()->commissions->markCommissionsPaidForAffiliate((int)$payout->affiliateId, (int)$payout->id);

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        Craft::info("Payout #{$payout->id} completed: {$payout->amount} {$payout->currency} for affiliate #{$payout->affiliateId}", __METHOD__);

        $this->trigger(self::EVENT_AFTER_PROCESS_PAYOUT, new PayoutEvent([
            'payout' => $payout,
            'affiliate' => $affiliate,
        ]));

        return true;
    }

    public function failPayout(PayoutElement $payout, ?string $notes = null): bool
    {
        if (!in_array($payout->payoutStatus, [PayoutElement::STATUS_PENDING, PayoutElement::STATUS_PROCESSING], true)) {
            return false;
        }

        $payout->payoutStatus = PayoutElement::STATUS_FAILED;
        if ($notes !== null) {
            $payout->notes = $notes;
        }

        if (!Craft::$app->getElements()->saveElement($payout, false)) {
            return false;
        }

        Craft::warning("Payout #{$payout->id} failed for affiliate #{$payout->affiliateId}" . ($notes ? ": {$notes}" : ''), __METHOD__);

        return true;
    }

    /**
     * Mark a completed payout as reversed (e.g. gateway reversal webhook).
     * Restores the affiliate's pending balance and unlinks its commissions.
     * Status-conditioned UPDATE prevents double-restore on retries.
     */
    public function markReversed(PayoutElement $payout, ?string $gatewayReversalId = null): bool
    {
        if ($payout->payoutStatus !== PayoutElement::STATUS_COMPLETED) {
            return false;
        }

        $affiliate = KickBack::getInstance()->affiliates->getAffiliateById($payout->affiliateId);
        if ($affiliate === null) {
            return false;
        }

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();
        try {
            $newNotes = trim(($payout->notes ?? '') . "\nReversed by gateway: " . ($gatewayReversalId ?? 'manual'));

            $affectedRows = $db->createCommand()
                ->update(
                    '{{%kickback_payouts}}',
                    [
                        'status' => PayoutElement::STATUS_REVERSED,
                        'notes' => $newNotes,
                    ],
                    [
                        'and',
                        ['id' => $payout->id],
                        ['status' => PayoutElement::STATUS_COMPLETED],
                    ],
                )
                ->execute();

            if ($affectedRows === 0) {
                $transaction->rollBack();
                Craft::info("Payout #{$payout->id} markReversed lost the status race - already resolved", __METHOD__);
                return false;
            }

            $payout->payoutStatus = PayoutElement::STATUS_REVERSED;
            $payout->notes = $newNotes;
            Craft::$app->getElements()->saveElement($payout, false);

            KickBack::getInstance()->affiliates->addPendingBalance($affiliate, (float)$payout->amount);
            KickBack::getInstance()->commissions->unlinkCommissionsFromPayout((int)$payout->id);

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        Craft::warning("Payout #{$payout->id} reversed ({$payout->amount} {$payout->currency} restored to affiliate #{$affiliate->id})", __METHOD__);
        return true;
    }

    public function cancelPayout(PayoutElement $payout): bool
    {
        return Craft::$app->getElements()->deleteElementById($payout->id);
    }

    public function getPayoutById(int $id): ?PayoutElement
    {
        return PayoutElement::find()->id($id)->one();
    }

    /**
     * Look up a payout by a gateway reference (transfer id, batch id).
     */
    public function findByGatewayReference(string $reference): ?PayoutElement
    {
        // PayPal echoes our payout UID back in sender_item_id; Stripe echoes its
        // own transaction/payout id, which we store in transactionId or gatewayBatchId.
        return PayoutElement::find()
            ->where([
                'or',
                ['kickback_payouts.transactionId' => $reference],
                ['kickback_payouts.gatewayBatchId' => $reference],
                ['elements.uid' => $reference],
            ])
            ->one();
    }

    /**
     * @return PayoutElement[]
     */
    public function getPayoutsByAffiliateId(int $affiliateId): array
    {
        return PayoutElement::find()
            ->affiliateId($affiliateId)
            ->orderBy(['elements.dateCreated' => SORT_DESC])
            ->all();
    }

    /**
     * @return PayoutElement[]
     */
    public function getAllPayouts(?string $status = null): array
    {
        $query = PayoutElement::find()
            ->orderBy(['elements.dateCreated' => SORT_DESC]);

        if ($status !== null) {
            $query->payoutStatus($status);
        }

        return $query->all();
    }

    /**
     * @return AffiliateElement[]
     */
    public function getEligibleAffiliates(): array
    {
        /** @var AffiliateElement[] */
        return AffiliateElement::find()
            ->affiliateStatus(AffiliateElement::STATUS_ACTIVE)
            ->andWhere(['>=', 'kickback_affiliates.pendingBalance', KickBack::getInstance()->getSettings()->minimumPayoutAmount])
            ->all();
    }

    /**
     * Pure decision: should auto-run fire for the given cadence? $now injected for testability.
     */
    public function shouldAutoRun(string $cadence, ?\DateTimeInterface $lastRun, \DateTimeInterface $now): bool
    {
        if ($lastRun !== null && $lastRun->format('Y-m-d') === $now->format('Y-m-d')) {
            return false;
        }

        $S = \anvildev\craftkickback\models\Settings::class;
        return match ($cadence) {
            $S::CADENCE_WEEKLY => (int)$now->format('N') === 1,
            $S::CADENCE_BIWEEKLY => (int)$now->format('N') === 1 && ($lastRun === null || (int)$now->diff($lastRun)->days >= 14),
            $S::CADENCE_MONTHLY => (int)$now->format('j') === 1,
            $S::CADENCE_QUARTERLY => (int)$now->format('j') === 1 && in_array((int)$now->format('n'), [1, 4, 7, 10], true),
            default => false,
        };
    }

    public function recordAutoRun(): bool
    {
        $p = KickBack::getInstance();
        $s = $p->getSettings();
        $s->batchAutoProcessLastRun = DateHelper::nowString();
        return Craft::$app->getPlugins()->savePluginSettings($p, $s->toArray());
    }

    /**
     * @return PayoutElement[]
     */
    public function createBatchPayouts(?string $notes = null): array
    {
        return array_values(array_filter(
            array_map(fn($a) => $this->createPayout($a, $notes), $this->getEligibleAffiliates()),
        ));
    }

    public function processPayout(PayoutElement $payout): bool
    {
        if ($payout->payoutStatus !== PayoutElement::STATUS_PENDING) {
            return false;
        }

        $plugin = KickBack::getInstance();

        if (!$this->isVerifiedIfRequired($payout)) {
            return false;
        }

        $affiliate = $plugin->affiliates->getAffiliateById($payout->affiliateId);
        if ($affiliate === null) {
            return false;
        }

        $gateway = $plugin->payoutGateways->getGateway($payout->method);
        if ($gateway === null || !$gateway->isConfigured()) {
            return false;
        }

        $event = new PayoutEvent([
            'payout' => $payout,
            'affiliate' => $affiliate,
        ]);
        $this->trigger(self::EVENT_BEFORE_PROCESS_PAYOUT, $event);

        if (!$event->isValid) {
            return false;
        }

        $payout->payoutStatus = PayoutElement::STATUS_PROCESSING;
        Craft::$app->getElements()->saveElement($payout, false);

        $result = $gateway->processPayout($payout, $affiliate);

        return $this->handleGatewayResult($payout, $result);
    }

    /**
     * @param PayoutElement[] $payouts
     * @return array<int, bool>
     */
    public function processBatchViaGateways(array $payouts): array
    {
        $plugin = KickBack::getInstance();
        $results = [];
        $grouped = [];
        foreach ($payouts as $p) {
            $grouped[$p->method][] = $p;
        }

        foreach ($grouped as $method => $batch) {
            $gw = $plugin->payoutGateways->getGateway($method);
            if ($gw === null || !$gw->isConfigured()) {
                foreach ($batch as $p) {
                    $results[$p->id] = false;
                }
                continue;
            }

            $items = [];
            foreach ($batch as $p) {
                $aff = $this->payoutReadyAffiliate($p, $plugin);
                if ($aff === null) {
                    $results[$p->id] = false;
                    continue;
                }
                $p->payoutStatus = PayoutElement::STATUS_PROCESSING;
                Craft::$app->getElements()->saveElement($p, false);
                $items[] = ['payout' => $p, 'affiliate' => $aff];
            }
            if (empty($items)) {
                continue;
            }

            foreach ($gw->processBatch($items) as $i => $gwResult) {
                $results[$items[$i]['payout']->id] = $this->handleGatewayResult($items[$i]['payout'], $gwResult);
            }
        }

        return $results;
    }

    /**
     * Pre-flight: payout pending, verification satisfied, affiliate exists.
     */
    private function payoutReadyAffiliate(PayoutElement $payout, KickBack $plugin): ?AffiliateElement
    {
        if ($payout->payoutStatus !== PayoutElement::STATUS_PENDING || !$this->isVerifiedIfRequired($payout)) {
            return null;
        }

        return $plugin->affiliates->getAffiliateById($payout->affiliateId);
    }

    /**
     * True if verification is disabled or an approved approval row exists.
     */
    private function isVerifiedIfRequired(PayoutElement $payout): bool
    {
        $plugin = KickBack::getInstance();
        if (!$plugin->getSettings()->requirePayoutVerification) {
            return true;
        }

        $approval = $plugin->approvals->getFor('payout', (int)$payout->id);
        if ($approval === null || $approval->status !== ApprovalRecord::STATUS_APPROVED) {
            Craft::info(
                "Payout #{$payout->id} blocked - verification required but approval is "
                . ($approval === null ? 'missing' : $approval->status),
                __METHOD__,
            );
            return false;
        }

        return true;
    }

    private function handleGatewayResult(PayoutElement $payout, PayoutResult $result): bool
    {
        if (!$result->success) {
            $this->failPayout($payout, $result->errorMessage);
            return false;
        }

        if ($result->batchId !== null) {
            $payout->gatewayBatchId = $result->batchId;
        }

        // Async gateways return STATUS_PENDING; final resolution via webhook.
        if ($result->gatewayStatus === PayoutElement::STATUS_PENDING) {
            $result->transactionId !== null && $payout->transactionId = $result->transactionId;
            Craft::$app->getElements()->saveElement($payout, false);
            Craft::info("Payout #{$payout->id} submitted to gateway; awaiting async resolution" . ($result->batchId !== null ? " (batch {$result->batchId})" : ''), __METHOD__);
            return true;
        }

        return $this->completePayout($payout, $result->transactionId);
    }
}
