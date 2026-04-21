<?php

declare(strict_types=1);

namespace anvildev\craftkickback\elements\db;

use anvildev\craftkickback\elements\PayoutElement;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;

/**
 * @method PayoutElement|null one($db = null)
 * @method PayoutElement[] all($db = null)
 *
 * @extends ElementQuery<int, PayoutElement>
 */
class PayoutQuery extends ElementQuery
{
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

    protected function statusCondition(string $status): mixed
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
            ? ['kickback_payouts.status' => $map[$status]]
            : parent::statusCondition($status);
    }
}
