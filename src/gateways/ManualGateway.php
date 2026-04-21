<?php

declare(strict_types=1);

namespace anvildev\craftkickback\gateways;

use anvildev\craftkickback\elements\AffiliateElement;
use anvildev\craftkickback\elements\PayoutElement;

class ManualGateway implements PayoutGatewayInterface
{
    use DefaultBatchProcessingTrait;

    public function getHandle(): string
    {
        return 'manual';
    }

    public function getDisplayName(): string
    {
        return 'Manual';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function isAffiliateReady(AffiliateElement $affiliate): bool
    {
        return true;
    }

    public function processPayout(PayoutElement $payout, AffiliateElement $affiliate): PayoutResult
    {
        return PayoutResult::succeeded('manual_' . $payout->id);
    }
}
