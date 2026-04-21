<?php

declare(strict_types=1);

namespace anvildev\craftkickback\gateways;

/**
 * Default {@see PayoutGatewayInterface::processBatch()} implementation for
 * gateways that have no batched upstream API - loops over the items and
 * delegates each to {@see PayoutGatewayInterface::processPayout()}. Gateways
 * with a real batch endpoint (e.g. PayPal Payouts) override instead.
 */
trait DefaultBatchProcessingTrait
{
    /**
     * @param array<int, array{payout: \anvildev\craftkickback\elements\PayoutElement, affiliate: \anvildev\craftkickback\elements\AffiliateElement}> $items
     * @return PayoutResult[]
     */
    public function processBatch(array $items): array
    {
        return array_map(
            fn($item) => $this->processPayout($item['payout'], $item['affiliate']),
            $items,
        );
    }
}
