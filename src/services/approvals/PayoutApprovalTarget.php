<?php

declare(strict_types=1);

namespace anvildev\craftkickback\services\approvals;

use anvildev\craftkickback\elements\PayoutElement;
use anvildev\craftkickback\KickBack;
use Craft;
use craft\helpers\UrlHelper;

/**
 * Makes a payout reviewable through the approvals queue. The per-instance
 * cache + handler reuse collapse exists/label/details/creator lookups to one
 * DB hit per payout across a full queue render.
 */
class PayoutApprovalTarget implements ApprovalTargetInterface
{
    /** @var array<int, PayoutElement|false> */
    private array $payoutCache = [];

    public function getCreatorUserId(int $targetId): ?int
    {
        $payout = $this->findPayout($targetId);
        return $payout?->createdByUserId;
    }

    public function onReject(int $targetId): void
    {
        $payout = $this->findPayout($targetId);
        if ($payout === null) {
            return;
        }

        $payout->payoutStatus = PayoutElement::STATUS_REJECTED;
        Craft::$app->getElements()->saveElement($payout, false);
    }

    public function getRowLabel(int $targetId): string
    {
        $payout = $this->findPayout($targetId);
        if ($payout === null) {
            return Craft::t('kickback', 'Payout #{id} (missing)', ['id' => $targetId]);
        }

        return Craft::t('kickback', 'Payout #{id} · {amount}', [
            'id' => $payout->id,
            'amount' => Craft::$app->getFormatter()->asCurrency((float)$payout->amount, $payout->currency),
        ]);
    }

    public function getRowUrl(int $targetId): string
    {
        return UrlHelper::cpUrl("kickback/payouts/{$targetId}");
    }

    public function exists(int $targetId): bool
    {
        return $this->findPayout($targetId) !== null;
    }

    public function getRowDetails(int $targetId): array
    {
        $payout = $this->findPayout($targetId);
        if ($payout === null) {
            return [
                'amount' => null,
                'method' => null,
                'affiliate' => null,
                'createdBy' => null,
            ];
        }

        $affiliate = $payout->affiliateId !== null
            ? KickBack::getInstance()->affiliates->getAffiliateById($payout->affiliateId)
            : null;

        $creator = $payout->createdByUserId !== null
            ? Craft::$app->getUsers()->getUserById($payout->createdByUserId)
            : null;

        return [
            'amount' => Craft::$app->getFormatter()->asCurrency((float)$payout->amount, $payout->currency),
            'method' => ucfirst(str_replace('_', ' ', $payout->method)),
            'affiliate' => $affiliate?->title,
            'createdBy' => $creator?->friendlyName,
        ];
    }

    private function findPayout(int $id): ?PayoutElement
    {
        $cached = $this->payoutCache[$id] ??= KickBack::getInstance()->payouts->getPayoutById($id) ?? false;
        return $cached === false ? null : $cached;
    }
}
