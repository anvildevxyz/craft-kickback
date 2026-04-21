<?php

declare(strict_types=1);

namespace anvildev\craftkickback\gateways;

/**
 * Gateways that receive async resolution events from the provider implement
 * this interface. Stripe (Connect events), PayPal (payout batch webhooks),
 * etc. Manual/store-credit/bank-transfer do not - they resolve synchronously
 * or out-of-band.
 */
interface WebhookHandlerInterface
{
    /**
     * Verify a signed webhook payload and, if valid, mutate the corresponding
     * payout state (complete / reverse / fail) via PayoutService.
     *
     * Implementations MUST:
     *  - Verify the signature with `hash_equals()` - never `==`
     *  - Return `WebhookResult::unverified()` on bad signatures
     *  - Be idempotent: retries from the provider must not double-apply
     *  - Look up the payout by the gateway's own reference (transfer id,
     *    batch id) - NEVER trust an id from the payload body alone
     *
     * @param array<string, string> $headers
     */
    public function handleWebhook(string $rawBody, array $headers): WebhookResult;
}
