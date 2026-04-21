# Payout Gateways

Kickback ships with PayPal and Stripe gateway implementations. You can add custom gateways by implementing the `PayoutGatewayInterface`.

## PayoutGatewayInterface

**Namespace:** `anvildev\craftkickback\gateways\PayoutGatewayInterface`

```php
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
```

## PayoutResult

An immutable value object representing the result of a gateway payout attempt.

```php
use anvildev\craftkickback\gateways\PayoutResult;

// Success
PayoutResult::succeeded(string $transactionId, ?string $batchId = null): self

// Failure
PayoutResult::failed(string $errorMessage): self

// Pending (for async gateways like PayPal batches)
PayoutResult::pending(string $batchId): self
```

### Properties

| Property | Type | Description |
|---|---|---|
| `success` | `bool` | Whether the payout was accepted |
| `transactionId` | `?string` | Gateway transaction identifier |
| `errorMessage` | `?string` | Error message on failure |
| `gatewayStatus` | `?string` | Gateway-specific status string |
| `batchId` | `?string` | Batch identifier (PayPal) |

## Built-in Gateways

### PayPalGateway

Uses the PayPal Payouts SDK to send batch payouts.

**Handle:** `paypal`

**Required settings:** `paypalClientId`, `paypalClientSecret`

**Affiliate requirement:** `paypalEmail` must be set

**Behavior:**
- All payouts are sent as a single batch request (even single payouts)
- Results are asynchronous - returns `PayoutResult::pending()` with a batch ID
- Supports sandbox mode via `paypalSandbox` setting

### StripeGateway

Uses the Stripe API to send transfers to connected Express accounts.

**Handle:** `stripe`

**Required settings:** `stripeSecretKey`

**Affiliate requirement:** `stripeAccountId` must be set and account must have completed onboarding

**Behavior:**
- Payouts are synchronous - result is immediate success or failure
- No batch API - `processBatch()` loops individual transfers
- Amounts are converted to cents for the Stripe API
- Includes metadata: `kickback_payout_id`, `kickback_affiliate_id`

**Additional methods (not on the interface):**

```php
$stripeGw = KickBack::getInstance()->payoutGateways->getStripeGateway();

// Create a Stripe Express connected account for an affiliate
$stripeGw->createConnectedAccount(AffiliateElement $affiliate): ?string

// Generate a Stripe-hosted onboarding link
$stripeGw->createOnboardingLink(string $accountId, string $refreshUrl, string $returnUrl): ?string

// Check if a connected account has completed onboarding
$stripeGw->isAccountReady(string $accountId): bool
```

## Extending the gateway registry

`PayoutGatewayService::init()` registers the three built-in gateways
(`paypal`, `stripe`, `manual`) into a private array. There is no public
registration API in 1.x, so adding a custom gateway requires either
subclassing `PayoutGatewayService` and binding your subclass via Craft's
component config, or patching the plugin directly. The
`PayoutGatewayInterface` and `PayoutResult` contracts above are the stable
part of the API; the registry itself is not.

## Webhooks and reconciliation

### Endpoint

**Route:** `POST /kickback/webhooks/<handle>`

`<handle>` must match `[a-z][a-z0-9_-]*` - the same string returned by the gateway's `getHandle()` method.

CSRF validation is disabled on this controller; providers don't send CSRF tokens. Authenticity is enforced by each gateway's own signature verification (e.g. Stripe verifies the `Stripe-Signature` header via `hash_equals` inside `Webhook::constructEvent`).

**Responses:**

| Condition | Status |
|---|---|
| Verified and payout updated | 200 `ok` |
| Verified but payout not found locally | 200 `ok` (stops provider retrying) |
| Unknown gateway handle | 404 |
| Gateway exists but doesn't implement `WebhookHandlerInterface` | 400 |
| Signature verification failed | 400 |

### Stripe setup

1. In the Stripe dashboard, create a webhook endpoint pointing at your site and copy the signing secret (`whsec_...`).
2. Add the secret to `.env`:
   ```
   CRAFT_STRIPE_WEBHOOK_SECRET=whsec_...
   ```
   Any env var name works - the setting is read via `App::parseEnv()`.
3. In Kickback → Settings → Payment gateways, set **Stripe webhook secret** to `$CRAFT_STRIPE_WEBHOOK_SECRET`.
4. Point the webhook at `https://your-site.tld/kickback/webhooks/stripe`.
5. Subscribe to these events: `transfer.reversed`, `transfer.failed`.
   Do **not** subscribe to `transfer.created` - the plugin records that synchronously at transfer creation time, so re-delivery is noise.

### Deployment: rate limit the webhook path

The endpoint is unauthenticated and CSRF-disabled by design. Craft's built-in rate limiter only applies to authenticated sessions, so there is no PHP-layer throttling here. Apply a per-IP rate limit at the web server or load balancer on the `/kickback/webhooks/*` path before traffic reaches PHP.

Example nginx config:

```nginx
limit_req_zone $binary_remote_addr zone=kickback_webhooks:10m rate=30r/m;

location ^~ /kickback/webhooks/ {
    limit_req zone=kickback_webhooks burst=10 nodelay;
    limit_req_status 429;
    try_files $uri /index.php?$query_string;
}
```

30 requests per minute per IP with a burst of 10; overflow returns 429. The only legitimate callers are your gateway providers, so the threshold can be aggressive. Tune to match your provider's retry schedule.

### Reconciliation

Webhooks are best-effort. Stripe and PayPal can drop delivery during incidents, leaving a reversed payout sitting as `completed` indefinitely. The reconciliation job is the safety net.

```bash
craft kickback/reconcile/run --days=7
```

- Walks every payout with `status = completed` and `elements.dateUpdated >= now - N days`.
- For each payout whose gateway implements `ReconciliationCapableInterface`, calls `fetchPayoutStatus` and reverses via `PayoutService::markReversed` when the gateway reports `'reversed'`.
- Streams results via `->each(100)` - memory-safe for large installs.
- Logs a `Craft::info` summary on completion: `"Reconciliation: checked N payouts, reversed M, unknown U"`.
- `--days < 1` is rejected with a non-zero exit code (`ExitCode::USAGE`).

Suggested cron (daily at 3 AM):

```
0 3 * * * craft kickback/reconcile/run --days=7
```

Run with a wider window (`--days=30`) occasionally to catch older drift.

### Implementing a webhook handler for a new gateway

1. Implement `WebhookHandlerInterface` on the gateway class. The method signature is:
   ```php
   public function handleWebhook(string $rawBody, array $headers): WebhookResult;
   ```
2. Verify the signature using `hash_equals()` - never `==`. Provider SDKs (e.g. Stripe's `Webhook::constructEvent`) typically wrap this; if you're hand-rolling, call `hash_equals()` explicitly.
3. Look up the payout via `PayoutService::findByGatewayReference($ref)`, using a reference the gateway itself generated (transfer id, batch id). Never trust an id from the payload body alone - that field is attacker-controlled.
4. Return `WebhookResult::unverified($reason)` on a bad signature. The controller logs a warning and returns 400.
5. Return `WebhookResult::verified(processed: false)` when the event is valid but refers to a payout that isn't ours (e.g. another system on the same Stripe account). The controller returns 200 so the provider stops retrying.
6. Return `WebhookResult::verified(processed: true, payoutId: (string)$payout->id)` on success.
7. Mutate local state via `PayoutService` methods (`markReversed`, `failPayout`, `completePayout`). These are idempotent under the status-conditioned UPDATE pattern - a retry racing another retry produces one state transition, not two.

### PayPal-specific notes

PayPal payouts are active as of 1.1. The gateway implements all three
opt-in interfaces (`PayoutGatewayInterface`, `WebhookHandlerInterface`,
`ReconciliationCapableInterface`) and ships behind the same
`Settings::$paypalSandbox` toggle as before. Configure
`paypalClientId`, `paypalClientSecret`, and `paypalWebhookId` in the CP
settings (or `.env` via `App::parseEnv`), then point the PayPal
dashboard webhook at `https://your-site.tld/kickback/webhooks/paypal`
and subscribe to the six `PAYMENT.PAYOUTS-ITEM.*` events.

The webhook handler implementation clears four PayPal-specific
hurdles on top of the generic checklist above:

- **Signature verification is a round-trip.** Unlike Stripe's HMAC check
  (which is a pure function of the secret + payload), PayPal's signature
  verification requires a `POST /v1/notifications/verify-webhook-signature`
  call to PayPal's API with the transmission headers plus the raw event
  body. That call can fail for transient reasons unrelated to the
  signature - network timeouts, PayPal outage, rate limits. Return
  `WebhookResult::unverified(...)` on any verification API failure, and
  rely on the reconciliation job to catch up if the webhook was
  legitimately dropped.

- **Subscribe to six events.** PayPal splits payout resolution across
  `PAYMENT.PAYOUTS-ITEM.SUCCEEDED`, `PAYMENT.PAYOUTS-ITEM.FAILED`,
  `PAYMENT.PAYOUTS-ITEM.RETURNED`, `PAYMENT.PAYOUTS-ITEM.UNCLAIMED`,
  `PAYMENT.PAYOUTS-ITEM.BLOCKED`, and `PAYMENT.PAYOUTS-ITEM.DENIED`.
  Map SUCCEEDED to `PayoutService::completePayout`; map the remaining
  five to `PayoutService::failPayout` with the event type as the reason
  string.

- **Trust only sender_* fields for lookup.** Never look up a local payout
  by the PayPal-side `payouts_item.payout_item_id` or `batch_header.
  payout_batch_id`. Those are PayPal's own ids. Look up by
  `payouts_item.sender_item_id` (set to the local payout uid at batch
  create time) or `payouts_batch.sender_batch_id`. The sender_* fields
  are the ones WE generate, so they're tamper-resistant in the usual
  ways.

- **Add `stripeWebhookSecret`'s twin.** Settings needs
  `paypalWebhookId` (PayPal's webhook identifier, not a secret - but
  required for the verification API call). Read via `App::parseEnv` so
  it can live in `.env`. Validation rule joins the existing
  gateway-credentials string-validation list.

### Implementing reconciliation for a new gateway

1. Implement `ReconciliationCapableInterface` on the gateway class. The method signature is:
   ```php
   public function fetchPayoutStatus(PayoutElement $payout): string;
   ```
2. Return one of three literal strings: `'completed'`, `'reversed'`, or `'unknown'`.
3. Return `'unknown'` on any transient error (network failure, auth error, missing transaction id). The job logs and continues; the next run retries.
4. Look up the transfer by the `transactionId` stored on the payout - not by any value passed in from the reconciliation job.
5. `ReconcilePayoutsJob` automatically picks up any gateway that implements the interface. No registration is needed beyond the class.
