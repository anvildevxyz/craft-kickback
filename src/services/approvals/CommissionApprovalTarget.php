<?php

declare(strict_types=1);

namespace anvildev\craftkickback\services\approvals;

use anvildev\craftkickback\KickBack;
use anvildev\craftkickback\records\CommissionRecord;
use Craft;
use craft\helpers\UrlHelper;

/**
 * Makes a commission reviewable through the approvals queue.
 */
class CommissionApprovalTarget implements ApprovalTargetInterface
{
    /** @var array<int, CommissionRecord|false> */
    private array $commissionCache = [];

    public function getCreatorUserId(int $targetId): ?int
    {
        // Commissions are created automatically, so self-verification cannot apply.
        return null;
    }

    public function onReject(int $targetId): void
    {
        $commission = $this->findCommission($targetId);
        if ($commission === null) {
            return;
        }

        KickBack::getInstance()->commissions->rejectCommission($commission);
    }

    public function getRowLabel(int $targetId): string
    {
        $commission = $this->findCommission($targetId);
        if ($commission === null) {
            return Craft::t('kickback', 'Commission #{id} (missing)', ['id' => $targetId]);
        }

        return Craft::t('kickback', 'Commission #{id} · {amount}', [
            'id' => $commission->id,
            'amount' => Craft::$app->getFormatter()->asCurrency((float)$commission->amount, $commission->currency),
        ]);
    }

    public function getRowUrl(int $targetId): string
    {
        // No per-commission edit page - link to the index for filtering.
        return UrlHelper::cpUrl('kickback/commissions');
    }

    public function exists(int $targetId): bool
    {
        return $this->findCommission($targetId) !== null;
    }

    public function getRowDetails(int $targetId): array
    {
        $commission = $this->findCommission($targetId);
        if ($commission === null) {
            return [
                'amount' => null,
                'method' => null,
                'affiliate' => null,
                'createdBy' => null,
            ];
        }

        $affiliate = $commission->affiliateId !== null
            ? KickBack::getInstance()->affiliates->getAffiliateById($commission->affiliateId)
            : null;

        return [
            'amount' => Craft::$app->getFormatter()->asCurrency((float)$commission->amount, $commission->currency),
            'method' => ucfirst($commission->rateType ?? ''),
            'affiliate' => $affiliate?->title,
            'createdBy' => null,
        ];
    }

    private function findCommission(int $id): ?CommissionRecord
    {
        $cached = $this->commissionCache[$id] ??= KickBack::getInstance()->commissions->getCommissionById($id) ?? false;
        return $cached === false ? null : $cached;
    }
}
