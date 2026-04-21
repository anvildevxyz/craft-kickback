<?php

declare(strict_types=1);

namespace anvildev\craftkickback\elements\db;

use anvildev\craftkickback\elements\CommissionElement;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;

/**
 * @method CommissionElement|null one($db = null)
 * @method CommissionElement[] all($db = null)
 *
 * @extends ElementQuery<int, CommissionElement>
 */
class CommissionQuery extends ElementQuery
{
    public ?int $referralId = null;
    public ?int $affiliateId = null;
    public ?string $commissionStatus = null;
    public ?int $payoutId = null;

    public function referralId(?int $value): self
    {
        $this->referralId = $value;
        return $this;
    }

    public function affiliateId(?int $value): self
    {
        $this->affiliateId = $value;
        return $this;
    }

    public function commissionStatus(?string $value): self
    {
        $this->commissionStatus = $value;
        return $this;
    }

    public function payoutId(?int $value): self
    {
        $this->payoutId = $value;
        return $this;
    }

    protected function beforePrepare(): bool
    {
        $this->joinElementTable('kickback_commissions');

        $this->query->select([
            'kickback_commissions.referralId',
            'kickback_commissions.affiliateId',
            'kickback_commissions.amount',
            'kickback_commissions.originalAmount',
            'kickback_commissions.currency',
            'kickback_commissions.rate',
            'kickback_commissions.rateType',
            'kickback_commissions.ruleApplied',
            'kickback_commissions.ruleResolutionTrace',
            'kickback_commissions.tier',
            'kickback_commissions.status as commissionStatus',
            'kickback_commissions.payoutId',
            'kickback_commissions.description',
            'kickback_commissions.dateApproved',
            'kickback_commissions.dateReversed',
        ]);

        $params = [
            'referralId' => $this->referralId,
            'affiliateId' => $this->affiliateId,
            'status' => $this->commissionStatus,
            'payoutId' => $this->payoutId,
        ];
        foreach ($params as $col => $val) {
            if ($val !== null) {
                $this->subQuery->andWhere(Db::parseParam("kickback_commissions.$col", $val));
            }
        }

        return parent::beforePrepare();
    }

    protected function statusCondition(string $status): mixed
    {
        $map = [
            CommissionElement::STATUS_PENDING => 'pending',
            CommissionElement::STATUS_APPROVED => 'approved',
            CommissionElement::STATUS_PAID => 'paid',
            CommissionElement::STATUS_REVERSED => 'reversed',
            CommissionElement::STATUS_REJECTED => 'rejected',
        ];
        return isset($map[$status])
            ? ['kickback_commissions.status' => $map[$status]]
            : parent::statusCondition($status);
    }
}
