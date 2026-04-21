# Configuration

All settings live on `anvildev\craftkickback\models\Settings` and are edited
via the CP settings screen (`kickback/settings`) or via a `config/kickback.php`
file if you prefer code-managed config. Access in PHP:

```php
$settings = \anvildev\craftkickback\KickBack::getInstance()->getSettings();
```

## Commissions

| Property | Default | Notes |
|---|---|---|
| `defaultCommissionType` | `'percentage'` | `'percentage'` or `'flat'`. Used when no program, group, or rule override applies. See below. |
| `defaultCommissionRate` | `10.0` | Fallback commission rate. Interpretation depends on `defaultCommissionType`. |
| `excludeShippingFromCommission` | `true` | Subtract shipping before computing commission. |
| `excludeTaxFromCommission` | `true` | Subtract tax before computing commission. |
| `reverseCommissionOnRefund` | `true` | When a Commerce transaction is refunded, reverse the associated commissions proportionally. |

### Commission type: percentage vs. fixed amount

The commission type setting appears in multiple places - global defaults,
programs, affiliate groups, and commission rules. It controls how the rate
value is interpreted by `CommissionService::calculateAmount()`:

- **Percentage** (`percentage`): Commission = order line subtotal × (rate / 100).
  A rate of `10` on a $200 order produces a **$20** commission.
- **Fixed amount** (`flat`): Commission = the rate value itself, regardless of
  order size. A rate of `10` means **$10** per order whether the order is $50
  or $5,000. Applied once per order, not per line item.

The rate resolution chain (affiliate override → bonus rule → tiered rule →
group → program → global default) determines which rate and type pair is
used. Once resolved, the calculation is always one of these two formulas.

## Tracking & attribution

| Property | Default | Notes |
|---|---|---|
| `cookieDuration` | `30` | Days the referral cookie sticks. |
| `cookieName` | `'_kb_ref'` | Name of the tracking cookie. |
| `attributionModel` | `'last_click'` | One of `first_click`, `last_click`, `linear`. Determines which affiliate gets credit when a customer clicks multiple referral links before purchasing. See [flows.md – Attribution models](flows.md#attribution-models) for detailed behavior and code paths. |
| `referralParamName` | `'ref'` | Fallback query-param name (`?ref=code`) captured silently on site GETs. Validated against `^[a-zA-Z_][a-zA-Z0-9_]*$`. Referral links always use the `/r/<code>` format. |
| `enableCouponTracking` | `true` | Attribute orders via Commerce coupon code usage. |

## Hold period & auto-approval

| Property | Default | Notes |
|---|---|---|
| `autoApproveAffiliates` | `false` | If true, new affiliate signups land as `active` instead of `pending`. |
| `autoApproveReferrals` | `false` | If true, referrals promote to approved after `holdPeriodDays`. Queued during Craft's GC via `ApproveHeldReferralsJob`. |
| `holdPeriodDays` | `30` | Days a referral stays pending before eligibility for auto-approval. |

## Multi-tier / MLM

| Property | Default | Notes |
|---|---|---|
| `enableMultiTier` | `false` | Master switch. When enabled: (1) affiliates can recruit sub-affiliates via a personal invite link, (2) a "Team" page appears in the affiliate portal, (3) parent affiliates receive override commissions when their sub-affiliates generate sales. Override rates come from **MLM Tier** commission rules - without these rules, parents earn nothing. See [flows.md – Two-tier recruiting](flows.md#7-two-tier-recruiting) for the full flow. |
| `maxMlmDepth` | `3` | How many levels up the recruiter chain earn on a single sale (1–10). Depth 3 means the direct recruiter (tier 2) and their recruiter (tier 3) each earn an override, provided matching MLM Tier rules exist for those tiers. |

## Lifetime commissions

| Property | Default | Notes |
|---|---|---|
| `enableLifetimeCommissions` | `false` | If true, an affiliate keeps earning on every future order from a referred customer, not just the first one. Lookup goes through `kickback_customer_links`. |

## Fraud detection

| Property | Default | Notes |
|---|---|---|
| `enableFraudDetection` | `true` | Master switch. |
| `fraudClickVelocityThreshold` | `10` | Max clicks from one IP inside the velocity window before it's flagged. |
| `fraudClickVelocityWindow` | `60` | Window in minutes. |
| `fraudRapidConversionMinutes` | `5` | A conversion faster than this after a click is suspicious. |
| `fraudIpReuseThreshold` | `5` | Max distinct affiliates served from one IP before it's flagged. |
| `fraudAutoFlag` | `true` | If true, flagged referrals move straight to `flagged` status; if false they're only logged for manual review. |

## Payouts

| Property | Default | Notes |
|---|---|---|
| `minimumPayoutAmount` | `50.00` | Affiliates under this balance can't request or receive a payout. |
| `cancelledStatusHandles` | `['cancelled']` | Order status handles treated as cancelled for referral reversal. Multi-entry to handle sites that use multiple cancel-like statuses. |

### Batch auto-processing

| Property | Default | Notes |
|---|---|---|
| `batchAutoProcessEnabled` | `false` | Master switch for cron-driven batch payouts. |
| `batchAutoProcessCadence` | `'monthly'` | `weekly` (Monday), `biweekly` (every other Monday), `monthly` (1st), `quarterly` (1st of Jan/Apr/Jul/Oct). |
| `batchAutoProcessLastRun` | `null` | UTC timestamp of last auto-run. Do not edit manually - stamped by `PayoutService::recordAutoRun()`. |

Requires a crontab entry to check for scheduled runs. See [cron-setup.md](cron-setup.md) for
the crontab line, cadence semantics, dry-run testing, and monitoring.

### Payout verification (four-eyes)

| Property | Default | Notes |
|---|---|---|
| `requirePayoutVerification` | `false` | If true, payouts land as pending in the approvals queue and cannot be processed until a verifier approves them. |
| `defaultPayoutVerifierId` | `null` | Suggested assignee stamped on new approvals. Must be a real user with `kickback-verifyPayouts`; the Settings validator rejects anything else. Required when `requirePayoutVerification` is true. |
| `notifyVerifierOnRequest` | `true` | Email the verifier when a new payout enters the queue. |

## Payment gateways

| Property | Default | Notes |
|---|---|---|
| `paypalClientId` | `''` | Read via `App::parseEnv()`, so `$PAYPAL_CLIENT_ID` works. |
| `paypalClientSecret` | `''` | Same. Keep in `.env`. |
| `paypalSandbox` | `true` | Hit sandbox endpoints vs production. |
| `paypalWebhookId` | `''` | Required for inbound webhook signature verification. Leave empty to disable inbound webhooks entirely (payouts will remain in `processing` forever without this). |
| `stripeSecretKey` | `''` | `sk_test_...` or `sk_live_...`. Read via `App::parseEnv()`. |
| `stripeWebhookSecret` | `''` | `whsec_...` signing secret from the Stripe webhook configuration page. Required for inbound webhooks. |

See [gateways.md](gateways.md) for the gateway interface, webhook setup, and
implementation details.

## Affiliate portal (per-site)

| Property | Default | Notes |
|---|---|---|
| `affiliatePortalEnabledSites` | `[]` | Keyed by site handle; presence = enabled. The Settings form posts a lightswitch per site. |
| `affiliatePortalPaths` | `[]` | Keyed by site handle, value is the URL path segment (e.g. `'affiliate'`, `'partner'`, `'partners/program'`). Validated against `^[a-zA-Z0-9][a-zA-Z0-9/_-]*$`. |

On a fresh install, `afterInstall()` seeds the primary site into both maps
(path `affiliate`), so the portal is reachable at `/affiliate` immediately
after `plugin/install`. The seed only runs when both maps are empty - if you
supply either via `config/kickback.php` before install, the install respects
your config and skips the seed.

`Settings::getCurrentSitePortalPath()` is the canonical accessor - it returns
`null` unless the current site is both enabled AND has a non-empty path. Site
routes in `KickBack::registerSiteRoutes()` short-circuit on that.

## Validation notes

The settings model enforces several cross-field rules beyond simple type
validation:

- `maxMlmDepth` is clamped to 1–10.
- `referralParamName` must be a valid PHP-identifier-ish token.
- `defaultPayoutVerifierId` must exist and hold `kickback-verifyPayouts`;
  it's also **required** when `requirePayoutVerification` is true, because a
  missing verifier would make new approval requests land silently.
- Portal path segments are slug-like.
