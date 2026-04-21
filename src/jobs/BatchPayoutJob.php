<?php

declare(strict_types=1);

namespace anvildev\craftkickback\jobs;

use anvildev\craftkickback\elements\PayoutElement;
use anvildev\craftkickback\KickBack;
use Craft;
use craft\queue\BaseJob;

/**
 * Queue job: create payouts for all eligible affiliates and optionally
 * submit gateway-eligible ones to PayPal/Stripe.
 */
class BatchPayoutJob extends BaseJob
{
    public ?string $notes = null;
    public bool $autoProcess = false;

    public function execute($queue): void
    {
        $payouts = KickBack::getInstance()->payouts;
        $affiliates = $payouts->getEligibleAffiliates();
        $total = count($affiliates);

        if ($total === 0) {
            return;
        }

        $created = $failed = 0;
        $createdPayouts = [];

        foreach ($affiliates as $i => $affiliate) {
            $this->setProgress($queue, ($i + 1) / $total, "Processing affiliate {$affiliate->title}");
            try {
                if (($payout = $payouts->createPayout($affiliate, $this->notes)) !== null) {
                    $created++;
                    $createdPayouts[] = $payout;
                }
            } catch (\Throwable $e) {
                $failed++;
                Craft::error("Batch payout failed for affiliate #{$affiliate->id}: {$e->getMessage()}", __METHOD__);
            }
        }

        Craft::info("Batch payout complete: {$created} created, {$failed} failed out of {$total} eligible", __METHOD__);

        if ($this->autoProcess && $createdPayouts) {
            $gateway = array_filter($createdPayouts,
                fn($p) => in_array($p->method, [PayoutElement::METHOD_PAYPAL, PayoutElement::METHOD_STRIPE], true));
            if ($gateway) {
                $processed = count(array_filter($payouts->processBatchViaGateways($gateway)));
                Craft::info("Auto-processed {$processed}/" . count($gateway) . ' gateway payouts', __METHOD__);
            }
        }
    }

    protected function defaultDescription(): ?string
    {
        return Craft::t('kickback', 'Processing batch payouts');
    }
}
