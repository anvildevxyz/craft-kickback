<?php

declare(strict_types=1);

namespace anvildev\craftkickback\elements\db;

use anvildev\craftkickback\elements\AffiliateElement;
use Closure;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;
use Illuminate\Database\Query\Builder;

/**
 * @method AffiliateElement|null one($db = null)
 * @method AffiliateElement[] all($db = null)
 *
 * @extends ElementQuery<int, AffiliateElement>
 */
class AffiliateQuery extends ElementQuery
{
    /**
     * Declaring this real class-level property (not assigning it at
     * runtime) is what makes Craft 6 auto-join this table — it replaces
     * joinElementTable(), which beforePrepare() below still calls for
     * Craft 5. Craft 5's own ElementQuery has no such property, so this
     * is just an unused extra property there.
     */
    protected string $table = 'kickback_affiliates';

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

    /**
     * Craft 6 never calls beforePrepare() at all — this is the real hook
     * there. Translates the same select/filters above to the new
     * Illuminate\Database\Query\Builder ($this->query is no longer split
     * into query/subQuery, and has no andWhere()/joinElementTable()).
     */
    protected function elementQueryBeforeQuery(): void
    {
        parent::elementQueryBeforeQuery();

        $this->query->addSelect([
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
                $this->query->whereParam("kickback_affiliates.$col", $val);
            }
        }
    }

    protected function statusCondition(string $status): Closure
    {
        $map = [
            AffiliateElement::STATUS_ACTIVE => 'active',
            AffiliateElement::STATUS_PENDING => 'pending',
            AffiliateElement::STATUS_SUSPENDED => 'suspended',
            AffiliateElement::STATUS_REJECTED => 'rejected',
        ];
        return isset($map[$status])
            ? fn(Builder $q) => $q->where('kickback_affiliates.status', $map[$status])
            : parent::statusCondition($status);
    }
}
