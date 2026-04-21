<?php

declare(strict_types=1);

namespace anvildev\craftkickback\elements\db;

use anvildev\craftkickback\elements\AffiliateElement;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;

/**
 * @method AffiliateElement|null one($db = null)
 * @method AffiliateElement[] all($db = null)
 *
 * @extends ElementQuery<int, AffiliateElement>
 */
class AffiliateQuery extends ElementQuery
{
    public ?int $userId = null;
    public ?int $programId = null;
    public ?string $affiliateStatus = null;
    public ?int $groupId = null;
    public ?int $parentAffiliateId = null;
    public ?string $referralCode = null;
    public ?string $payoutMethod = null;

    public function userId(?int $value): self
    {
        $this->userId = $value;
        return $this;
    }

    public function programId(?int $value): self
    {
        $this->programId = $value;
        return $this;
    }

    public function affiliateStatus(?string $value): self
    {
        $this->affiliateStatus = $value;
        return $this;
    }

    public function groupId(?int $value): self
    {
        $this->groupId = $value;
        return $this;
    }

    public function parentAffiliateId(?int $value): self
    {
        $this->parentAffiliateId = $value;
        return $this;
    }

    public function referralCode(?string $value): self
    {
        $this->referralCode = $value;
        return $this;
    }

    public function payoutMethod(?string $value): self
    {
        $this->payoutMethod = $value;
        return $this;
    }

    protected function beforePrepare(): bool
    {
        $this->joinElementTable('kickback_affiliates');

        $this->query->select([
            'kickback_affiliates.userId',
            'kickback_affiliates.programId',
            'kickback_affiliates.status as affiliateStatus',
            'kickback_affiliates.referralCode',
            'kickback_affiliates.commissionRateOverride',
            'kickback_affiliates.commissionTypeOverride',
            'kickback_affiliates.parentAffiliateId',
            'kickback_affiliates.tierLevel',
            'kickback_affiliates.groupId',
            'kickback_affiliates.paypalEmail',
            'kickback_affiliates.stripeAccountId',
            'kickback_affiliates.payoutMethod',
            'kickback_affiliates.payoutThreshold',
            'kickback_affiliates.lifetimeEarnings',
            'kickback_affiliates.lifetimeReferrals',
            'kickback_affiliates.pendingBalance',
            'kickback_affiliates.notes',
            'kickback_affiliates.dateApproved',
        ]);

        $params = [
            'userId' => $this->userId,
            'programId' => $this->programId,
            'status' => $this->affiliateStatus,
            'groupId' => $this->groupId,
            'parentAffiliateId' => $this->parentAffiliateId,
            'referralCode' => $this->referralCode,
            'payoutMethod' => $this->payoutMethod,
        ];
        foreach ($params as $col => $val) {
            if ($val !== null) {
                $this->subQuery->andWhere(Db::parseParam("kickback_affiliates.$col", $val));
            }
        }

        return parent::beforePrepare();
    }

    protected function statusCondition(string $status): mixed
    {
        $map = [
            AffiliateElement::STATUS_ACTIVE => 'active',
            AffiliateElement::STATUS_PENDING => 'pending',
            AffiliateElement::STATUS_SUSPENDED => 'suspended',
            AffiliateElement::STATUS_REJECTED => 'rejected',
        ];
        return isset($map[$status])
            ? ['kickback_affiliates.status' => $map[$status]]
            : parent::statusCondition($status);
    }
}
