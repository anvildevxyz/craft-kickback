<?php

declare(strict_types=1);

namespace anvildev\craftkickback\gateways;

use anvildev\craftkickback\elements\AffiliateElement;
use anvildev\craftkickback\elements\PayoutElement;
use anvildev\craftkickback\KickBack;
use Craft;
use craft\helpers\App;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripeGateway implements PayoutGatewayInterface, WebhookHandlerInterface, ReconciliationCapableInterface
{
    use DefaultBatchProcessingTrait;

    private ?StripeClient $client = null;

    public function getHandle(): string
    {
        return 'stripe';
    }

    public function getDisplayName(): string
    {
        return 'Stripe';
    }

    public function isConfigured(): bool
    {
        $settings = KickBack::getInstance()->getSettings();

        return !empty(App::parseEnv($settings->stripeSecretKey));
    }

    public function isAffiliateReady(AffiliateElement $affiliate): bool
    {
        return !empty($affiliate->stripeAccountId) && $this->isAccountReady($affiliate->stripeAccountId);
    }

    public function processPayout(PayoutElement $payout, AffiliateElement $affiliate): PayoutResult
    {
        if (!$this->isConfigured()) {
            return PayoutResult::failed('Stripe gateway is not configured.');
        }

        if (empty($affiliate->stripeAccountId)) {
            return PayoutResult::failed('Affiliate has no Stripe connected account.');
        }

        try {
            $client = $this->getClient();
            $currency = strtolower($payout->currency ?: KickBack::getCommerceCurrency());

            $transfer = $client->transfers->create(
                [
                    'amount' => (int)round($payout->amount * 100),
                    'currency' => $currency,
                    'destination' => $affiliate->stripeAccountId,
                    'description' => 'Affiliate payout #' . $payout->id,
                    'metadata' => [
                        'kickback_payout_id' => $payout->id,
                        'kickback_affiliate_id' => $affiliate->id,
                    ],
                ],
                [
                    'idempotency_key' => 'kickback_payout_' . $payout->uid,
                ],
            );

            Craft::info("Stripe transfer created: {$transfer->id} for payout #{$payout->id}", __METHOD__);
            return PayoutResult::succeeded($transfer->id);
        } catch (ApiErrorException $e) {
            Craft::error("Stripe transfer failed for payout #{$payout->id}: {$e->getMessage()}", __METHOD__);
            return PayoutResult::failed('Stripe API error: ' . $e->getMessage());
        }
    }

    public function createConnectedAccount(AffiliateElement $affiliate): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            $client = $this->getClient();
            $user = $affiliate->getUser();

            $account = $client->accounts->create([
                'type' => 'express',
                'email' => $user?->email,
                'metadata' => [
                    'kickback_affiliate_id' => $affiliate->id,
                ],
            ]);

            Craft::info("Stripe Express account created: {$account->id} for affiliate #{$affiliate->id}", __METHOD__);
            return $account->id;
        } catch (ApiErrorException $e) {
            Craft::error("Stripe account creation failed for affiliate #{$affiliate->id}: {$e->getMessage()}", __METHOD__);
            return null;
        }
    }

    public function createOnboardingLink(string $accountId, string $refreshUrl, string $returnUrl): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            $client = $this->getClient();

            $link = $client->accountLinks->create([
                'account' => $accountId,
                'refresh_url' => $refreshUrl,
                'return_url' => $returnUrl,
                'type' => 'account_onboarding',
            ]);

            return $link->url;
        } catch (ApiErrorException $e) {
            Craft::error("Stripe onboarding link failed for account {$accountId}: {$e->getMessage()}", __METHOD__);
            return null;
        }
    }

    public function isAccountReady(string $accountId): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $client = $this->getClient();
            $account = $client->accounts->retrieve($accountId);

            return $account->charges_enabled && $account->payouts_enabled;
        } catch (ApiErrorException $e) {
            Craft::error("Stripe account check failed for {$accountId}: {$e->getMessage()}", __METHOD__);
            return false;
        }
    }

    private function getClient(): StripeClient
    {
        return $this->client ??= new StripeClient(App::parseEnv(KickBack::getInstance()->getSettings()->stripeSecretKey));
    }

    /**
     * @param array<string, string> $headers
     */
    public function handleWebhook(string $rawBody, array $headers): WebhookResult
    {
        $sigHeader = $headers['Stripe-Signature'] ?? $headers['stripe-signature'] ?? null;
        if ($sigHeader === null) {
            return WebhookResult::unverified('Missing Stripe-Signature header.');
        }

        try {
            $plugin = KickBack::getInstance();
        } catch (\Error) {
            $plugin = null;
        }
        if ($plugin === null) {
            return WebhookResult::unverified('Stripe webhook secret not configured.');
        }
        $secret = App::parseEnv($plugin->getSettings()->stripeWebhookSecret);
        if ($secret === '') {
            return WebhookResult::unverified('Stripe webhook secret not configured.');
        }

        try {
            $event = Webhook::constructEvent($rawBody, $sigHeader, $secret);
        } catch (SignatureVerificationException $e) {
            Craft::warning('Stripe webhook signature verification failed: ' . $e->getMessage(), __METHOD__);
            return WebhookResult::unverified('Signature verification failed.');
        } catch (\UnexpectedValueException $e) {
            Craft::warning('Stripe webhook payload was malformed: ' . $e->getMessage(), __METHOD__);
            return WebhookResult::unverified('Invalid payload.');
        }

        $transfer = $event->data->object ?? null;
        $payoutId = $transfer->metadata->kickback_payout_id ?? null;
        if ($payoutId === null) {
            return WebhookResult::verified(processed: false);
        }

        $payout = $plugin->payouts->getPayoutById((int)$payoutId);
        if ($payout === null) {
            return WebhookResult::verified(processed: false);
        }

        match ($event->type) {
            'transfer.reversed' => $plugin->payouts->markReversed($payout, (string)$transfer->id),
            'transfer.failed' => $plugin->payouts->failPayout($payout, 'Stripe reported transfer failed'),
            default => Craft::info("Unhandled Stripe event type: {$event->type}", __METHOD__),
        };

        return WebhookResult::verified(processed: true, payoutId: (string)$payout->id);
    }

    public function fetchPayoutStatus(PayoutElement $payout): string
    {
        if ($payout->transactionId === null) {
            return 'unknown';
        }
        try {
            $transfer = $this->getClient()->transfers->retrieve($payout->transactionId);
            if (!empty($transfer->reversed) || $transfer->amount_reversed >= $transfer->amount) {
                return 'reversed';
            }
            return 'completed';
        } catch (\Throwable $e) {
            Craft::warning("Reconciliation fetch failed for payout #{$payout->id}: {$e->getMessage()}", __METHOD__);
            return 'unknown';
        }
    }
}
