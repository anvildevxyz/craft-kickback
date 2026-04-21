<?php

declare(strict_types=1);

namespace anvildev\craftkickback\gateways;

use anvildev\craftkickback\elements\AffiliateElement;
use anvildev\craftkickback\elements\PayoutElement;

/**
 * Contract for payout gateway implementations that process affiliate payments.
 */
interface PayoutGatewayInterface
{
    /**
     * Gateway handle used for routing (e.g. 'paypal', 'stripe').
     */
    public function getHandle(): string;

    /**
     * Human-readable name for admin UI.
     */
    public function getDisplayName(): string;

    /**
     * Whether API credentials are configured and non-empty.
     */
    public function isConfigured(): bool;

    /**
     * Whether the affiliate has the required info for this gateway
     * (e.g. PayPal email, Stripe connected account).
     */
    public function isAffiliateReady(AffiliateElement $affiliate): bool;

    /**
     * Send a single payout via this gateway's API.
     */
    public function processPayout(PayoutElement $payout, AffiliateElement $affiliate): PayoutResult;

    /**
     * Send multiple payouts in a batch (if the provider supports it).
     *
     * @param array<array{payout: PayoutElement, affiliate: AffiliateElement}> $items
     * @return PayoutResult[]
     */
    public function processBatch(array $items): array;
}
