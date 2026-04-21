# Security

How Kickback protects against common web application threats. This document
covers the measures in place and the design decisions behind them.

## CSRF Protection

All state-changing CP and portal endpoints require `POST` with a valid Craft
CSRF token (`{{ csrfInput() }}` in templates, `$this->requirePostRequest()`
in controllers).

**Exception:** `WebhooksController` disables CSRF validation because payment
gateway webhooks (Stripe, PayPal) provide their own request authenticity via
signature verification. The controller delegates to each gateway's
`WebhookHandlerInterface::handleWebhook`, which verifies the signature before
processing.

## Authentication & Authorization

### CP Access

Every CP controller checks permissions in `beforeAction()`:

| Controller | Permission |
|-----------|------------|
| Programs | `kickback-managePrograms` |
| Affiliates | `kickback-manageAffiliates` |
| Commissions (view) | `kickback-manageCommissions` |
| Commissions (approve/reject) | `kickback-approveReferrals` |
| Referrals (view) | `kickback-manageReferrals` |
| Referrals (approve/reject) | `kickback-approveReferrals` |
| Payouts | `kickback-managePayouts` |
| Payouts (process) | `kickback-processPayouts` |
| Payouts (verify) | `kickback-verifyPayouts` |
| Settings | `kickback-manageSettings` |

### Portal (Frontend) Access

`PortalController::beforeAction()` enforces a strict scope:

1. Requires a logged-in Craft user (`requireLogin()`)
2. Looks up the affiliate by `userId` - never from a request parameter
3. Redirects `pending` affiliates to `/pending`
4. Throws `ForbiddenHttpException` for `suspended` or `rejected` affiliates
5. All portal actions operate on `$this->_affiliate` (the authenticated user's
   affiliate record), eliminating IDOR by construction

No portal endpoint accepts an `affiliateId` from the request body or URL.

## Cookie Security

The referral tracking cookie (`_kb_ref`) uses:

- **HMAC signing** via `Craft\Security::hashData` - tamper-proof
- **httpOnly: true** - inaccessible to JavaScript
- **secure: true** when the request is HTTPS
- **SameSite: Lax** - blocks cross-site POST-based cookie replay

The cookie value is validated against the HMAC on read. Tampered cookies are
discarded silently and treated as if no referral cookie exists.

## Rate Limiting

### Registration

`RegistrationController` enforces a sliding-window IP-based limit:
10 registration attempts per hour per IP, backed by Craft's cache. Exceeding
the limit returns a 429 response.

### Account Enumeration Prevention

Registration with an existing email produces the same "check your email"
response as a genuinely new signup. An attacker cannot determine whether an
email is already registered by observing the response.

## SQL Injection

All database operations use Craft's QueryBuilder API with parameterized
conditions. No raw SQL string interpolation exists in the codebase. Example:

```php
CommissionRecord::find()->where(['referralId' => $referralId]);
```

## XSS Prevention

### Template Output

All dynamic values in templates use Twig's auto-escaping. The `|raw` filter
is only used on controlled translation strings that contain intentional HTML
markup (e.g., bold site names in settings instructions).

### Inline JavaScript

All interactive behavior (copy-to-clipboard, confirm dialogs) uses
`data-*` attributes with `addEventListener` - no inline `onclick` handlers.
Translation strings interpolated into data attributes are escaped via
`|e('html_attr')`.

## Webhook Signature Verification

### Stripe

`StripeGateway::handleWebhook` uses `Stripe\Webhook::constructEvent` to
verify the `Stripe-Signature` header against `Settings::$stripeWebhookSecret`.
Invalid signatures return a generic "Signature verification failed" message to
the caller - the full exception is logged server-side only to prevent leaking
comparison fragments.

### PayPal

`PayPalGateway::handleWebhook` uses PayPal's HTTPS round-trip verification
endpoint (`/v1/notifications/verify-webhook-signature`) against
`Settings::$paypalWebhookId`. No local HMAC fallback exists - PayPal's API is
the sole source of truth.

## Payout Security

### Double-Spend Prevention

`PayoutService::createPayout` uses `SELECT ... FOR UPDATE` on the affiliate
row inside a transaction. Two concurrent requests cannot both create a payout
from the same balance.

### Completion Idempotency

`PayoutService::completePayout` uses a status-conditioned `UPDATE` - only one
concurrent caller can transition a payout from `processing` to `completed`.
The second caller sees zero affected rows and returns early.

### Gateway Idempotency

`StripeGateway::processPayout` passes a stable `Idempotency-Key` derived from
the payout element's `uid`. Queue retries after transient failures return the
existing Stripe transfer instead of creating a duplicate.

### Self-Verification Block

When payout verification is enabled, the user who created a payout
(`createdByUserId`) cannot approve it. The verification UI hides the approve
button and shows an explanation.

## CSV Export

`CsvExportHelper::streamAsDownload` sanitizes every cell via `sanitizeRow()`,
prepending a single apostrophe to cells beginning with `=`, `+`, `-`, `@`,
tab, or CR. This defuses formula injection across all CSV exports.

## Coupon Code Generation

Coupon codes use `bin2hex(random_bytes(2))` - cryptographically random -
inside a collision-avoidance loop that rejects any suffix already present
in `kickback_coupons`.

## Seed Data Safety

`SeedController` refuses to run outside Craft's `devMode`. This prevents
accidental or malicious data wipes in production via
`php craft kickback/seed --fresh`.

## Log Injection

Webhook error messages from Stripe/PayPal are sanitized with
`str_replace(["\r", "\n"], ' ', ...)` before interpolation into log entries,
preventing attackers from injecting fake log lines via crafted error messages.

## Reporting Vulnerabilities

If you discover a security issue, please email dev@anvil.xyz with a
description and reproduction steps. We aim to respond within 48 hours and
will coordinate disclosure.
