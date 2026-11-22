<?php

declare(strict_types=1);

namespace anvildev\craftkickback\elements\db;

use anvildev\craftkickback\elements\PayoutElement;
use Closure;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;
use Illuminate\Database\Query\Builder;

/**
 * @method PayoutElement|null one($db = null)
 * @method PayoutElement[] all($db = null)
 *
 * @extends ElementQuery<int, PayoutElement>
 */
class PayoutQuery extends ElementQuery
{
    /**
     * Declaring this real class-level property (not assigning it at
     * runtime) is what makes Craft 6 auto-join this table — it replaces
     * joinElementTable(), which beforePrepare() below still calls for
     * Craft 5. Craft 5's own ElementQuery has no such property, so this
     * is just an unused extra property there.
     */
    protected string $table = 'kickback_payouts';

    public ?int $affiliateId = null;
    public ?string $payoutStatus = null;
    public ?string $method = null;
    public ?string $verificationStatus = null;

    public function affiliateId(?int $value): self
    {
        $this->affiliateId = $value;
        return $this;
    }

    public function payoutStatus(?string $value): self
    {
        $this->payoutStatus = $value;
        return $this;
    }

    public function method(?string $value): self
    {
        $this->method = $value;
        return $this;
    }

    public function verificationStatus(?string $value): static
    {
        $this->verificationStatus = $value;
        return $this;
    }

    protected function beforePrepare(): bool
    {
        $this->joinElementTable('kickback_payouts');

        $this->query->select([
            'kickback_payouts.affiliateId',
            'kickback_payouts.createdByUserId',
            'kickback_payouts.amount',
            'kickback_payouts.currency',
            'kickback_payouts.method',
            'kickback_payouts.status as payoutStatus',
            'kickback_payouts.transactionId',
            'kickback_payouts.gatewayBatchId',
            'kickback_payouts.notes',
            'kickback_payouts.processedAt',
        ]);

        foreach (['affiliateId' => $this->affiliateId, 'status' => $this->payoutStatus, 'method' => $this->method] as $col => $val) {
            if ($val !== null) {
                $this->subQuery->andWhere(Db::parseParam("kickback_payouts.$col", $val));
            }
        }

        if ($this->verificationStatus !== null) {
            $this->subQuery->leftJoin(
                '{{%kickback_approvals}} kickback_approvals',
                "[[kickback_approvals.targetType]] = 'payout' AND [[kickback_approvals.targetId]] = [[kickback_payouts.id]]",
            );
            $this->subQuery->andWhere([
                'kickback_approvals.status' => $this->verificationStatus,
            ]);
        }

        return parent::beforePrepare();
    }

    /**
     * Craft 6 never calls beforePrepare() at all — this is the real hook
     * there. Translates the same select/joins/filters above to the new
     * Illuminate\Database\Query\Builder ($this->query is no longer split
     * into query/subQuery, and has no andWhere()/joinElementTable()).
     */
    protected function elementQueryBeforeQuery(): void
    {
        parent::elementQueryBeforeQuery();

        $this->query->addSelect([
            'kickback_payouts.affiliateId',
            'kickback_payouts.createdByUserId',
            'kickback_payouts.amount',
            'kickback_payouts.currency',
            'kickback_payouts.method',
            'kickback_payouts.status as payoutStatus',
            'kickback_payouts.transactionId',
            'kickback_payouts.gatewayBatchId',
            'kickback_payouts.notes',
            'kickback_payouts.processedAt',
        ]);

        foreach (['affiliateId' => $this->affiliateId, 'status' => $this->payoutStatus, 'method' => $this->method] as $col => $val) {
            if ($val !== null) {
                $this->query->whereParam("kickback_payouts.$col", $val);
            }
        }

        if ($this->verificationStatus !== null) {
            $this->query->leftJoin('kickback_approvals', function($join) {
                $join->on('kickback_approvals.targetId', '=', 'kickback_payouts.id')
                    ->where('kickback_approvals.targetType', '=', 'payout');
            });
            $this->query->where('kickback_approvals.status', $this->verificationStatus);
        }
    }

    protected function statusCondition(string $status): Closure
    {
        $map = [
            PayoutElement::STATUS_PENDING => 'pending',
            PayoutElement::STATUS_PROCESSING => 'processing',
            PayoutElement::STATUS_COMPLETED => 'completed',
            PayoutElement::STATUS_FAILED => 'failed',
            PayoutElement::STATUS_REJECTED => 'rejected',
            PayoutElement::STATUS_REVERSED => 'reversed',
        ];
        return isset($map[$status])
            ? fn(Builder $q) => $q->where('kickback_payouts.status', $map[$status])
            : parent::statusCondition($status);
    }
}
