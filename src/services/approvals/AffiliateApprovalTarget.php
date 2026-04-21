<?php

declare(strict_types=1);

namespace anvildev\craftkickback\services\approvals;

use anvildev\craftkickback\elements\AffiliateElement;
use anvildev\craftkickback\KickBack;
use Craft;
use craft\helpers\UrlHelper;

/**
 * Makes an affiliate application reviewable through the approvals queue.
 */
class AffiliateApprovalTarget implements ApprovalTargetInterface
{
    /** @var array<int, AffiliateElement|false> */
    private array $affiliateCache = [];

    public function getCreatorUserId(int $targetId): ?int
    {
        return $this->findAffiliate($targetId)?->userId;
    }

    public function onReject(int $targetId): void
    {
        $affiliate = $this->findAffiliate($targetId);
        if ($affiliate === null) {
            return;
        }

        KickBack::getInstance()->affiliates->rejectAffiliate($affiliate);
    }

    public function getRowLabel(int $targetId): string
    {
        $affiliate = $this->findAffiliate($targetId);
        if ($affiliate === null) {
            return Craft::t('kickback', 'Affiliate #{id} (missing)', ['id' => $targetId]);
        }

        return Craft::t('kickback', 'Affiliate #{id} · {name}', [
            'id' => $affiliate->id,
            'name' => $affiliate->title,
        ]);
    }

    public function getRowUrl(int $targetId): string
    {
        return UrlHelper::cpUrl("kickback/affiliates/{$targetId}");
    }

    public function exists(int $targetId): bool
    {
        return $this->findAffiliate($targetId) !== null;
    }

    public function getRowDetails(int $targetId): array
    {
        $affiliate = $this->findAffiliate($targetId);
        if ($affiliate === null) {
            return [
                'status' => null,
                'referralCode' => null,
                'affiliate' => null,
                'createdBy' => null,
            ];
        }

        $user = $affiliate->userId !== null
            ? Craft::$app->getUsers()->getUserById($affiliate->userId)
            : null;

        return [
            'status' => ucfirst($affiliate->affiliateStatus ?? ''),
            'referralCode' => $affiliate->referralCode,
            'affiliate' => $affiliate->title,
            'createdBy' => $user?->friendlyName,
        ];
    }

    private function findAffiliate(int $id): ?AffiliateElement
    {
        // false sentinel = "looked up, missing" (distinct from "not yet looked up").
        $cached = $this->affiliateCache[$id] ??= KickBack::getInstance()->affiliates->getAffiliateById($id) ?? false;
        return $cached === false ? null : $cached;
    }
}
