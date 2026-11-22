<?php

declare(strict_types=1);

namespace anvildev\craftkickback\elements\db;

use anvildev\craftkickback\elements\ReferralElement;
use Closure;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;
use Illuminate\Database\Query\Builder;

/**
 * @method ReferralElement|null one($db = null)
 * @method ReferralElement[] all($db = null)
 *
 * @extends ElementQuery<int, ReferralElement>
 */
class ReferralQuery extends ElementQuery
{
    /**
     * Declaring this real class-level property (not assigning it at
     * runtime) is what makes Craft 6 auto-join this table — it replaces
     * joinElementTable(), which beforePrepare() below still calls for
     * Craft 5. Craft 5's own ElementQuery has no such property, so this
     * is just an unused extra property there.
     */
    protected string $table = 'kickback_referrals';

    public ?int $affiliateId = null;
    public ?int $programId = null;
    public ?int $orderId = null;
    public ?string $referralStatus = null;
    public ?string $customerEmail = null;
    public ?string $couponCode = null;

    public function affiliateId(?int $value): self
    {
        $this->affiliateId = $value;
        return $this;
    }

    public function programId(?int $value): self
    {
        $this->programId = $value;
        return $this;
    }

    public function orderId(?int $value): self
    {
        $this->orderId = $value;
        return $this;
    }

    public function referralStatus(?string $value): self
    {
        $this->referralStatus = $value;
        return $this;
    }

    public function customerEmail(?string $value): self
    {
        $this->customerEmail = $value;
        return $this;
    }

    public function couponCode(?string $value): self
    {
        $this->couponCode = $value;
        return $this;
    }

    protected function beforePrepare(): bool
    {
        $this->joinElementTable('kickback_referrals');

        $this->query->select([
            'kickback_referrals.affiliateId',
            'kickback_referrals.programId',
            'kickback_referrals.orderId',
            'kickback_referrals.clickId',
            'kickback_referrals.customerEmail',
            'kickback_referrals.customerId',
            'kickback_referrals.orderSubtotal',
            'kickback_referrals.status as referralStatus',
            'kickback_referrals.attributionMethod',
            'kickback_referrals.couponCode',
            'kickback_referrals.referralResolutionTrace',
            'kickback_referrals.fraudFlags',
            'kickback_referrals.dateApproved',
            'kickback_referrals.datePaid',
        ]);

        $params = [
            'affiliateId' => $this->affiliateId,
            'programId' => $this->programId,
            'orderId' => $this->orderId,
            'status' => $this->referralStatus,
            'customerEmail' => $this->customerEmail,
            'couponCode' => $this->couponCode,
        ];
        foreach ($params as $col => $val) {
            if ($val !== null) {
                $this->subQuery->andWhere(Db::parseParam("kickback_referrals.$col", $val));
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
            'kickback_referrals.affiliateId',
            'kickback_referrals.programId',
            'kickback_referrals.orderId',
            'kickback_referrals.clickId',
            'kickback_referrals.customerEmail',
            'kickback_referrals.customerId',
            'kickback_referrals.orderSubtotal',
            'kickback_referrals.status as referralStatus',
            'kickback_referrals.attributionMethod',
            'kickback_referrals.couponCode',
            'kickback_referrals.referralResolutionTrace',
            'kickback_referrals.fraudFlags',
            'kickback_referrals.dateApproved',
            'kickback_referrals.datePaid',
        ]);

        $params = [
            'affiliateId' => $this->affiliateId,
            'programId' => $this->programId,
            'orderId' => $this->orderId,
            'status' => $this->referralStatus,
            'customerEmail' => $this->customerEmail,
            'couponCode' => $this->couponCode,
        ];
        foreach ($params as $col => $val) {
            if ($val !== null) {
                $this->query->whereParam("kickback_referrals.$col", $val);
            }
        }
    }

    protected function statusCondition(string $status): Closure
    {
        $map = [
            ReferralElement::STATUS_PENDING => 'pending',
            ReferralElement::STATUS_APPROVED => 'approved',
            ReferralElement::STATUS_REJECTED => 'rejected',
            ReferralElement::STATUS_PAID => 'paid',
            ReferralElement::STATUS_FLAGGED => 'flagged',
        ];
        return isset($map[$status])
            ? fn(Builder $q) => $q->where('kickback_referrals.status', $map[$status])
            : parent::statusCondition($status);
    }
}
