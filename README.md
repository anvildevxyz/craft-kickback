# Kickback

Advanced affiliate and referral marketing for [Craft CMS](https://craftcms.com/) 5 with [Craft Commerce](https://craftcms.com/commerce) integration.

## Features

- **Affiliate management** - custom element type with statuses, groups, approval workflows, and multi-tier (MLM) support
- **Click tracking** - cookie-based attribution with pretty URLs (`/r/{code}`), configurable cookie duration, and first-click or last-click models
- **Referral tracking** - automatic order attribution via cookie, coupon code, or lifetime customer linking
- **Commission engine** - percentage or flat-rate commissions with tiered rules, product/category overrides, and multi-tier MLM payouts
- **Fraud detection** - click velocity checks, rapid conversion detection, IP reuse analysis, duplicate customer flagging, and suspicious user-agent detection
- **Payouts** - manual, PayPal batch, and Stripe Connect payouts with minimum thresholds and batch processing
- **Affiliate portal** - front-end dashboard where affiliates view stats, referrals, commissions, creatives, coupons, and manage payout settings
- **Multisite programs and creatives** - program names, descriptions, terms and creative content (name, HTML, link text, destination URL) are translatable per Craft site with automatic fallback to primary-site content for untranslated sites
- **Coupon integration** - affiliates can generate discount coupons that also track attribution
- **Creatives** - manage marketing assets with auto-generated affiliate URLs
- **Reports & exports** - dashboard charts, date-range filtering, top affiliates, and CSV export for referrals, commissions, and payouts
- **Events** - custom events for affiliates, commissions, referrals, fraud, and payouts to hook into from other plugins or modules

## Requirements

- PHP 8.2+
- Craft CMS 5.8.0+
- Craft Commerce (for order/subscription tracking)

## Installation

Install via Composer:

```bash
composer require anvildev/craft-kickback
```

Then install the plugin in Craft:

```bash
php craft plugin/install kickback
```

Or install from **Settings > Plugins** in the control panel.

## Configuration

### Plugin Settings

Configure via **Settings > Plugins > Kickback** in the CP, or create a `config/kickback.php` file:

```php
<?php

return [
    // Commission defaults
    'defaultCommissionType' => 'percentage',   // 'percentage' or 'flat'
    'defaultCommissionRate' => 10.0,

    // Multi-tier / MLM
    'enableMultiTier' => false,
    'maxMlmDepth' => 3,

    // Lifetime commissions
    'enableLifetimeCommissions' => false,

    // Tracking
    'referralParamName' => 'ref',
    'cookieName' => '_kb_ref',
    'cookieDuration' => 30,                    // days
    'attributionModel' => 'last_click',        // 'first_click' or 'last_click'
    'enableCouponTracking' => true,
    'clickRetentionDays' => 90,                // clicks older than N days are pruned during GC

    // Approval
    'autoApproveAffiliates' => false,
    'autoApproveReferrals' => false,
    'holdPeriodDays' => 30,

    // Payouts
    'minimumPayoutAmount' => 50.00,
    'batchAutoProcessEnabled' => false,
    'batchAutoProcessCadence' => 'monthly',    // 'weekly', 'biweekly', 'monthly'

    // Payout verification (four-eyes review) - enable in production
    'requirePayoutVerification' => false,
    'defaultPayoutVerifierId' => null,         // user id of the default verifier
    'notifyVerifierOnRequest' => true,

    // Commission calculation
    'excludeShippingFromCommission' => true,
    'excludeTaxFromCommission' => true,
    'reverseCommissionOnRefund' => true,

    // Fraud detection
    'enableFraudDetection' => true,
    'fraudAutoFlag' => true,
    'fraudClickVelocityThreshold' => 10,       // clicks
    'fraudClickVelocityWindow' => 60,          // minutes
    'fraudRapidConversionMinutes' => 5,
    'fraudIpReuseThreshold' => 5,

    // Coupons
    'enableCouponCreation' => true,
    'maxCouponsPerAffiliate' => 5,
    'allowAffiliateSelfServiceCoupons' => false,
    'maxSelfServiceDiscountPercent' => 50.0,

    // Affiliate portal (per-site)
    'affiliatePortalPaths' => ['default' => 'affiliate'],
    'affiliatePortalEnabledSites' => ['default' => true],

    // Order status handles that trigger commission reversal
    'cancelledStatusHandles' => ['cancelled'],

    // Payment gateways
    'paypalClientId' => '$PAYPAL_CLIENT_ID',
    'paypalClientSecret' => '$PAYPAL_CLIENT_SECRET',
    'paypalSandbox' => true,
    'paypalWebhookId' => '$PAYPAL_WEBHOOK_ID',        // required for inbound webhook signature verification
    'stripeSecretKey' => '$STRIPE_SECRET_KEY',
    'stripeWebhookSecret' => '$STRIPE_WEBHOOK_SECRET', // required for inbound webhook signature verification
];
```

## How It Works

### Referral Flow

1. A visitor clicks an affiliate's referral link (`/r/{code}` or `?ref={code}`)
2. Kickback records the click and sets a tracking cookie
3. When the visitor completes a Commerce order, Kickback matches the cookie (or coupon code) to an affiliate
4. A **referral** is created and, optionally, evaluated for fraud
5. **Commissions** are calculated based on the program's rules (product, category, tiered, or default rate)
6. After the hold period, referrals and commissions can be approved (manually or automatically)
7. Approved commissions accumulate in the affiliate's pending balance
8. When the balance meets the minimum threshold, a **payout** can be created and processed

### Attribution Methods

| Method | How it works |
|---|---|
| **Cookie** | Visitor clicked a referral link; tracked via browser cookie |
| **Coupon** | Customer used a coupon code linked to an affiliate |
| **Lifetime customer** | Customer was previously referred and is linked permanently |
| **Direct link** | Referral code present in the URL at checkout |

### Commission Rules

Commission rules are evaluated in priority order. The first matching rule determines the rate:

| Rule Type | Description |
|---|---|
| **Product** | Override rate for a specific product |
| **Category** | Override rate for a product category |
| **Tiered** | Rate changes based on affiliate performance thresholds |
| **Bonus** | One-time bonus commissions |
| **MLM tier** | Different rates per tier level in multi-tier setups |

If no rule matches, the affiliate's override rate is used, falling back to the program's default rate.

## Affiliate Portal

Kickback includes a full front-end portal for affiliates. On a fresh install it's auto-enabled on the primary site at `/affiliate`; additional sites and custom paths are configured under **Settings → Kickback → Portal** (or in `config/kickback.php`). The portal includes:

| Page | Path | Description |
|---|---|---|
| Dashboard | `/affiliate` | Stats overview: clicks, referrals, earnings, pending balance |
| Links | `/affiliate/links` | Referral URL with copy-to-clipboard |
| Referrals | `/affiliate/referrals` | Filterable list of referrals |
| Commissions | `/affiliate/commissions` | Filterable list of commissions |
| Coupons | `/affiliate/coupons` | Generate and manage discount coupons (max 5) |
| Creatives | `/affiliate/creatives` | Marketing assets with affiliate-specific URLs |
| Settings | `/affiliate/settings` | Payout method, PayPal email, Stripe onboarding, payout threshold |

Once they hold an affiliate record, affiliates must be logged-in Craft users to access the portal dashboard, commissions, payouts, and settings screens. Pending affiliates are redirected to a waiting page. Suspended or rejected affiliates are blocked.

### Registration

Registration at `/affiliate/register` is **publicly accessible** - anonymous visitors can fill out the form without first creating a Craft user account. Submitting the form creates a Craft user and an affiliate record in one step, then signs the visitor in automatically.

The form collects basic account details (first name, last name, email, password) alongside the affiliate-specific fields (program, payout method, PayPal email, optional parent referral code for multi-tier programs). Existing Craft users who visit the page while logged in skip the account section and only see the affiliate fields.

New affiliates are set to **pending** by default unless `autoApproveAffiliates` is enabled. If Craft's global "require email verification" flag is on, new Craft users get the standard activation email and must verify before they can log in - the affiliate record is still created and waits for them.

The form is protected by a honeypot field (bots that fill a hidden `website` input are silently redirected to the homepage). There is no plugin-level rate limiting; rely on your WAF or Craft's session-based throttling if spam becomes an issue.

## Payment Gateways

### PayPal

Processes batch payouts via the PayPal Payouts SDK. Affiliates provide their PayPal email address in their portal settings.

Configure with `paypalClientId`, `paypalClientSecret`, and `paypalSandbox` settings.

### Stripe Connect

Uses Stripe Express connected accounts. Affiliates complete onboarding through a Stripe-hosted flow directly from their portal settings page. Transfers are synchronous, but reversal/failure notifications arrive via webhook.

Configure with `stripeSecretKey` and `stripeWebhookSecret` settings.

### Manual

Payouts can always be completed manually from the CP by entering an optional transaction ID reference.

### Webhook Setup (async gateways)

PayPal and Stripe return final payout status asynchronously via webhooks. Configure the public endpoints in each provider's dashboard:

| Gateway | URL template |
|---|---|
| PayPal | `https://<your-site>/kickback/webhooks/paypal` |
| Stripe | `https://<your-site>/kickback/webhooks/stripe` |

Then set the matching signing secret in **Settings → Payment Gateways**:

- **PayPal Webhook ID** (e.g. `WH-XXXXX...`) - used to verify inbound signatures via PayPal's `/v1/notifications/verify-webhook-signature` API. Leave empty to disable inbound webhooks entirely (the plugin will refuse to trust unsigned callbacks).
- **Stripe Webhook Secret** (e.g. `whsec_...`) - the signing secret from Stripe's webhook configuration page.

Without these secrets, payouts stay in `processing` forever - the async resolution never lands. Signature verification is not optional.

Subscribed events:
- **PayPal:** `PAYMENT.PAYOUTS-ITEM.SUCCEEDED`, `PAYMENT.PAYOUTS-ITEM.FAILED`, `PAYMENT.PAYOUTS-ITEM.BLOCKED`, `PAYMENT.PAYOUTS-ITEM.DENIED`, `PAYMENT.PAYOUTS-ITEM.RETURNED`, `PAYMENT.PAYOUTS-ITEM.UNCLAIMED` - subscribe to all PayPal Payouts events to avoid dropped outcomes.
- **Stripe:** `transfer.reversed`, `transfer.failed`. Stripe Connect transfers complete synchronously, so only reversal / failure notifications are webhook-driven.

## Production Recommendations

Before enabling live payouts:

- **Enable payout verification** (four-eyes approvals). Set `requirePayoutVerification` → `true` in plugin settings and assign a `defaultPayoutVerifierId`. Once enabled, every payout requires a second CP user with the `kickback-verifyPayouts` permission to approve it before the gateway is called. This is your safety net against misconfigured affiliate details, duplicate payouts from gateway mishaps, or compromised operator accounts. The verifier cannot be the same user who created the payout.
- **Restrict the `Process Payouts` permission** to a small group. Once the payout is released to the gateway, it cannot be reversed through the plugin - only via manual refund in the gateway dashboard.
- **Configure `cancelledStatusHandles`** to match your Commerce order-status handles for cancellations/refunds so commissions reverse correctly when customers cancel.
- **Set `clickRetentionDays`** to a reasonable window (default 90). Clicks older than this window are pruned during Craft's garbage collection.
- **Run the `kickback/health/check` console command in your deploy pipeline.** It exits non-zero on bootstrap problems (missing tables, unreachable gateway credentials, misconfigured permissions).
- **Verify webhook reachability** (PayPal/Stripe) from the public internet before sending real payouts. A firewalled webhook endpoint leaves payouts stuck in `processing`.
- **Smoke-test one live payout per gateway** before enabling batch automation.

## CP Permissions

Kickback registers granular permissions that can be assigned to user groups:

| Permission | Description |
|---|---|
| Manage Affiliates | View and edit affiliate elements |
| Approve Affiliates | Approve or reject pending affiliates |
| Manage Referrals | View referrals and access fraud detection |
| Approve Referrals | Approve or reject referrals |
| Manage Commissions | View commissions and commission rules |
| Manage Payouts | View payouts |
| Process Payouts | Complete, fail, or process payouts via gateways |
| Manage Programs | Create, edit, and delete programs |
| View Reports | Access the reports dashboard |
| Manage Settings | Access plugin settings |

## Events

Kickback fires events you can listen to from a custom module or plugin:

```php
use anvildev\craftkickback\services\AffiliateService;
use anvildev\craftkickback\services\CommissionService;
use anvildev\craftkickback\services\FraudService;
use anvildev\craftkickback\events\AffiliateEvent;
use anvildev\craftkickback\events\CommissionEvent;
use anvildev\craftkickback\events\FraudEvent;
use yii\base\Event;

// After an affiliate is approved
Event::on(
    AffiliateService::class,
    AffiliateService::EVENT_AFTER_APPROVE_AFFILIATE,
    function (AffiliateEvent $event) {
        $affiliate = $event->affiliate;
        // Send a welcome email, sync to CRM, etc.
    }
);

// After a commission is approved
Event::on(
    CommissionService::class,
    CommissionService::EVENT_AFTER_APPROVE_COMMISSION,
    function (CommissionEvent $event) {
        $commission = $event->commission;
        $affiliate = $event->affiliate;
    }
);

// After a referral is flagged for fraud
Event::on(
    FraudService::class,
    FraudService::EVENT_AFTER_FLAG_REFERRAL,
    function (FraudEvent $event) {
        $referral = $event->referral;
        $flags = $event->fraudFlags;
    }
);
```

**Available events:**

- **AffiliateService** - `beforeApproveAffiliate`, `afterApproveAffiliate`, `beforeRejectAffiliate`, `afterRejectAffiliate`, `beforeSuspendAffiliate`, `afterSuspendAffiliate`
- **CommissionService** - `beforeApproveCommission`, `afterApproveCommission`, `beforeRejectCommission`, `afterRejectCommission`, `beforeReverseCommission`, `afterReverseCommission`
- **ReferralService** - `beforeCreateReferral`, `afterCreateReferral`, `beforeApproveReferral`, `afterApproveReferral`, `beforeRejectReferral`, `afterRejectReferral`
- **FraudService** - `afterFlagReferral`, `afterApproveFlagged`, `afterRejectFlagged`
- **PayoutService** - `beforeCreatePayout`, `afterCreatePayout`, `beforeProcessPayout`, `afterProcessPayout`

## Twig Variables

Kickback exposes a `craft.kickback` variable for use in your templates:

```twig
{# Get the current logged-in affiliate #}
{% set affiliate = craft.kickback.currentAffiliate() %}

{% if affiliate %}
    <p>Welcome, {{ affiliate.title }}!</p>
    <p>Your referral link: {{ affiliate.getReferralUrl() }}</p>
    <p>Earnings: {{ affiliate.lifetimeEarnings|currency(craft.kickback.currency) }}</p>
{% endif %}

{# Get the store currency #}
{{ craft.kickback.currency }}
```

## Database Tables

Kickback creates the following tables on install:

| Table | Description |
|---|---|
| `kickback_programs` | Affiliate programs with default rates |
| `kickback_affiliate_groups` | Groups for organizing affiliates |
| `kickback_affiliates` | Affiliate element content (linked to `elements`) |
| `kickback_commission_rules` | Product, category, tiered, and bonus rules |
| `kickback_clicks` | Click tracking log |
| `kickback_referrals` | Order-to-affiliate attributions |
| `kickback_commissions` | Calculated commission amounts |
| `kickback_payouts` | Payout records and transaction references |
| `kickback_coupons` | Affiliate-generated discount codes |
| `kickback_customer_links` | Lifetime customer-to-affiliate mapping |
| `kickback_approvals` | Four-eyes review queue (polymorphic) |
| `kickback_programs_sites` | Per-site translatable program fields |

## Developer documentation

Everything above is the feature-level tour. The code-level reference lives
under [`docs/`](docs/README.md):

- [quickstart.md](docs/quickstart.md) - install to first commission in ten minutes
- [architecture.md](docs/architecture.md) - directory layout, element types, service layer
- [bootstrap.md](docs/bootstrap.md) - `KickBack::init()` registration order
- [routes.md](docs/routes.md) - every CP + site route, plus console commands
- [permissions.md](docs/permissions.md) - all 12 permissions and the manage/approve split
- [configuration.md](docs/configuration.md) - every `Settings` property with defaults and validation
- [flows.md](docs/flows.md) - end-to-end flows (click to payout, refund reversal, verification, portal onboarding)
- [models.md](docs/models.md) - data models, status lifecycles, relationships
- [services.md](docs/services.md) - service API reference
- [events.md](docs/events.md) - event constants and handler examples
- [gateways.md](docs/gateways.md) - `PayoutGatewayInterface` and custom gateways
- [graphql.md](docs/graphql.md) - GraphQL schema
- [cron-setup.md](docs/cron-setup.md) - scheduled batch payout runner
- [troubleshooting.md](docs/troubleshooting.md) - operator diagnostics for common issues
- [SECURITY.md](docs/SECURITY.md) - security posture and disclosure policy

## Development

```bash
# Code style check
composer check-cs

# Auto-fix code style
composer fix-cs

# Static analysis
composer phpstan
```

## License

Proprietary — see [LICENSE.md](LICENSE.md).
