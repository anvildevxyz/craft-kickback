# Data Models

All models extend `craft\base\Model` and live in `anvildev\craftkickback\models`. The `AffiliateElement` is the exception - it extends `craft\base\Element`.

## AffiliateElement

**Namespace:** `anvildev\craftkickback\elements\AffiliateElement`
**Extends:** `craft\base\Element`

The only custom element type in the plugin. Represents an affiliate linked to a Craft user.

### Properties

| Property | Type | Default | Description |
|---|---|---|---|
| `userId` | `?int` | `null` | Linked Craft user ID |
| `programId` | `?int` | `null` | Associated program ID |
| `affiliateStatus` | `string` | `'pending'` | Current status |
| `referralCode` | `string` | `''` | Unique referral code |
| `commissionRateOverride` | `?float` | `null` | Per-affiliate commission rate override |
| `commissionTypeOverride` | `?string` | `null` | Per-affiliate commission type override |
| `parentAffiliateId` | `?int` | `null` | Parent affiliate for MLM tiers |
| `tierLevel` | `int` | `1` | MLM tier level |
| `groupId` | `?int` | `null` | Affiliate group ID |
| `paypalEmail` | `?string` | `null` | PayPal email for payouts |
| `stripeAccountId` | `?string` | `null` | Stripe Connect account ID |
| `payoutMethod` | `string` | `'manual'` | Payout method: `paypal`, `stripe`, `manual` |
| `payoutThreshold` | `float` | `50.0` | Minimum balance for payout |
| `lifetimeEarnings` | `float` | `0.0` | Total earnings paid out |
| `lifetimeReferrals` | `int` | `0` | Total referral count |
| `pendingBalance` | `float` | `0.0` | Approved but unpaid balance |
| `notes` | `?string` | `null` | Admin notes |
| `dateApproved` | `?DateTime` | `null` | When the affiliate was approved |

### Status Lifecycle

```
pending → active (via approveAffiliate)
pending → rejected (via rejectAffiliate)
active → suspended (via suspendAffiliate)
suspended → active (via reactivateAffiliate)
```

### Status Constants

```php
AffiliateElement::STATUS_ACTIVE     // 'active'
AffiliateElement::STATUS_PENDING    // 'pending'
AffiliateElement::STATUS_SUSPENDED  // 'suspended'
AffiliateElement::STATUS_REJECTED   // 'rejected'
```

### Key Methods

```php
$affiliate->getUser(): ?User           // Get the linked Craft user
$affiliate->setUser(User $user): void  // Set the user (also sets userId)
$affiliate->getReferralUrl(?string $url): string  // Build referral URL
$affiliate->getStatus(): ?string       // Returns affiliateStatus
```

### Element Queries

```php
use anvildev\craftkickback\elements\AffiliateElement;

// Find by status
AffiliateElement::find()->affiliateStatus('active')->all();

// Find by referral code
AffiliateElement::find()->referralCode('john-doe')->one();

// Find by user
AffiliateElement::find()->userId(42)->one();
```

---

## Referral

Represents a referral linking an affiliate to a customer order.

### Properties

| Property | Type | Default | Description |
|---|---|---|---|
| `id` | `?int` | `null` | |
| `affiliateId` | `?int` | `null` | The referring affiliate |
| `programId` | `?int` | `null` | The program this referral belongs to |
| `orderId` | `?int` | `null` | Commerce order ID |
| `clickId` | `?int` | `null` | Originating click (if cookie-based) |
| `customerEmail` | `?string` | `null` | Customer's email |
| `customerId` | `?int` | `null` | Customer's Craft user ID |
| `orderSubtotal` | `float` | `0.0` | Order subtotal used for commission calculation |
| `status` | `string` | `'pending'` | Current status |
| `attributionMethod` | `string` | `'cookie'` | How the referral was attributed |
| `couponCode` | `?string` | `null` | Coupon code used (if coupon attribution) |
| `fraudFlags` | `?array` | `null` | Fraud detection flags |
| `dateApproved` | `?DateTime` | `null` | |
| `datePaid` | `?DateTime` | `null` | |

### Status Constants

```php
Referral::STATUS_PENDING   // 'pending'
Referral::STATUS_APPROVED  // 'approved'
Referral::STATUS_REJECTED  // 'rejected'
Referral::STATUS_PAID      // 'paid'
Referral::STATUS_FLAGGED   // 'flagged'
```

### Attribution Methods

| Value | Description |
|---|---|
| `cookie` | Standard cookie-based tracking via `?ref=` parameter |
| `coupon` | Attributed via a coupon code linked to an affiliate |
| `direct_link` | Direct referral link click |
| `lifetime_customer` | Returning customer linked to an affiliate |
| `manual` | Manually created referral |

---

## Commission

Represents a commission earned by an affiliate from a referral.

### Properties

| Property | Type | Default | Description |
|---|---|---|---|
| `id` | `?int` | `null` | |
| `referralId` | `?int` | `null` | Associated referral |
| `affiliateId` | `?int` | `null` | Earning affiliate |
| `amount` | `float` | `0.0` | Commission amount |
| `currency` | `string` | `'USD'` | ISO currency code |
| `rate` | `float` | `0.0` | The rate used |
| `rateType` | `string` | `''` | `'percentage'` or `'flat'` |
| `ruleApplied` | `?string` | `null` | Which rule resolved the rate |
| `tier` | `int` | `1` | MLM tier level |
| `status` | `string` | `'pending'` | Current status |
| `payoutId` | `?int` | `null` | Associated payout (when paid) |
| `description` | `?string` | `null` | Human-readable description |

### Status Constants

```php
Commission::STATUS_PENDING   // 'pending'
Commission::STATUS_APPROVED  // 'approved'
Commission::STATUS_PAID      // 'paid'
Commission::STATUS_REVERSED  // 'reversed'
Commission::STATUS_REJECTED  // 'rejected'
```

### Rate Type Constants

```php
Commission::RATE_TYPE_PERCENTAGE  // 'percentage'
Commission::RATE_TYPE_FLAT        // 'flat'
```

### Status Lifecycle

```
pending → approved (via approveCommission)
pending → rejected (via rejectCommission)
approved → reversed (via reverseCommission, e.g. on refund)
approved → paid (when included in a payout)
```

### Rule Applied Values

The `ruleApplied` field records which level of the priority chain resolved the rate:

| Value | Meaning |
|---|---|
| `affiliate_override` | Per-affiliate rate override |
| `rule:product:{name}` | Product-specific commission rule |
| `rule:category:{name}` | Category-specific commission rule |
| `group:{handle}` | Affiliate group rate |
| `program:{handle}` | Program default rate |
| `global_default` | Plugin settings default |
| `mlm_tier:{level}` | MLM tier commission |

---

## Payout

Represents a payout of earned commissions to an affiliate.

### Properties

| Property | Type | Default | Description |
|---|---|---|---|
| `id` | `?int` | `null` | |
| `affiliateId` | `?int` | `null` | Affiliate receiving the payout |
| `amount` | `float` | `0.0` | Payout amount |
| `currency` | `string` | `'USD'` | ISO currency code |
| `method` | `string` | `''` | Payment method |
| `payoutStatus` | `string` | `'pending'` | Current status (element-scoped field name; the underlying DB column is `status`) |
| `transactionId` | `?string` | `null` | Gateway transaction ID |
| `gatewayBatchId` | `?string` | `null` | Gateway batch ID (PayPal) |
| `notes` | `?string` | `null` | Admin notes or error messages |
| `processedAt` | `?DateTime` | `null` | When the payout was processed |

### Status Constants

```php
Payout::STATUS_PENDING     // 'pending'     - created, awaiting processing
Payout::STATUS_PROCESSING  // 'processing'  - submitted to gateway, awaiting async resolution
Payout::STATUS_COMPLETED   // 'completed'   - gateway confirmed success
Payout::STATUS_FAILED      // 'failed'      - gateway returned an error
Payout::STATUS_REJECTED    // 'rejected'    - verifier rejected pre-processing (terminal)
Payout::STATUS_REVERSED    // 'reversed'    - gateway later reversed the transfer
```

### Method Constants

```php
Payout::METHOD_PAYPAL   // 'paypal'
Payout::METHOD_STRIPE   // 'stripe'
Payout::METHOD_MANUAL   // 'manual'
```

### Status Lifecycle

```
pending → processing (when sent to gateway)
processing → completed (gateway success)
processing → failed (gateway error)
pending → deleted (via cancelPayout)
```

---

## Program

Represents an affiliate program with default commission settings.

### Properties

| Property | Type | Default | Description |
|---|---|---|---|
| `id` | `?int` | `null` | |
| `name` | `string` | `''` | Program name |
| `handle` | `string` | `''` | URL-safe handle |
| `description` | `?string` | `null` | |
| `defaultCommissionRate` | `float` | `10.0` | Default commission rate |
| `defaultCommissionType` | `string` | `'percentage'` | `'percentage'` or `'flat'` |
| `cookieDuration` | `int` | `30` | Cookie lifetime in days |
| `allowSelfReferral` | `bool` | `false` | Whether affiliates can refer themselves |
| `status` | `string` | `'active'` | |
| `termsAndConditions` | `?string` | `null` | |

### Status Constants

```php
Program::STATUS_ACTIVE    // 'active'
Program::STATUS_INACTIVE  // 'inactive'
Program::STATUS_ARCHIVED  // 'archived'
```

---

## CommissionRule

Represents a rule that determines commission rates for specific products, categories, or tiers.

### Properties

| Property | Type | Default | Description |
|---|---|---|---|
| `id` | `?int` | `null` | |
| `programId` | `?int` | `null` | Associated program |
| `name` | `string` | `''` | Rule name |
| `type` | `string` | `''` | Rule type |
| `targetId` | `?int` | `null` | Product or category ID |
| `commissionRate` | `float` | `0.0` | Commission rate |
| `commissionType` | `string` | `'percentage'` | `'percentage'` or `'flat'` |
| `tierThreshold` | `?int` | `null` | Referral count threshold for tiered rules |
| `tierLevel` | `?int` | `null` | MLM tier level |
| `lookbackDays` | `?int` | `null` | Lookback period for tiered rules |
| `priority` | `int` | `0` | Higher priority = matched first |
| `conditions` | `?array` | `null` | JSON conditions |

### Rule Types

| Type | Description |
|---|---|
| `product` | Applies to a specific product (matched by `targetId`) |
| `category` | Applies to a specific category (matched by `targetId`) |
| `tiered` | Applies based on referral count thresholds |
| `bonus` | One-time bonus rules |
| `mlm_tier` | MLM multi-tier commissions (matched by `tierLevel`) |

---

## Other Models

### AffiliateGroup

Groups affiliates with shared commission rate overrides. An affiliate belongs
to at most one group (via `AffiliateElement::$groupId`). When the commission
rate resolution chain runs (see [flows.md – Commission resolution](flows.md#1-click--referral--commission--payout)),
the group rate sits between tiered rules and the program default:

```
affiliate override → bonus rule → tiered rule → group rate → program default → global default
```

| Property | Type | Description |
|---|---|---|
| `name` | `string` | Group name (used as element title) |
| `handle` | `string` | URL-safe handle, unique across groups |
| `commissionRate` | `float` | Commission rate applied to affiliates in this group when no higher-priority rule matches |
| `commissionType` | `string` | `'percentage'` or `'flat'` |
| `sortOrder` | `int` | Controls display ordering in the CP element index and `getAllGroups()` API results. Has no effect on commission calculation - each affiliate belongs to one group via `groupId`, so group ordering is irrelevant for rate resolution. Useful for controlling the order groups appear in dropdowns or frontend listings. |

### Click

A tracked click on an affiliate referral link.

| Property | Type | Description |
|---|---|---|
| `affiliateId` | `?int` | The affiliate whose link was clicked |
| `programId` | `?int` | Associated program |
| `ip` | `string` | Visitor IP address |
| `userAgent` | `?string` | Browser user agent |
| `referrerUrl` | `?string` | HTTP referrer |
| `landingUrl` | `string` | Page the visitor landed on |
| `subId` | `?string` | Optional sub-tracking ID |
| `isUnique` | `bool` | Whether this was a unique visitor |

### Coupon

Links a coupon code to an affiliate for coupon-based referral tracking.

| Property | Type | Description |
|---|---|---|
| `affiliateId` | `?int` | Linked affiliate |
| `discountId` | `?int` | Commerce discount ID |
| `code` | `string` | Coupon code |
| `isVanity` | `bool` | Whether this is a vanity (custom) code |

### CustomerLink

Links a customer to an affiliate for lifetime commission tracking.

| Property | Type | Description |
|---|---|---|
| `affiliateId` | `?int` | The affiliate who referred this customer |
| `customerEmail` | `string` | Customer's email |
| `customerId` | `?int` | Customer's Craft user ID |

### Settings

Global plugin settings. See the plugin settings page for all options.

Key settings groups:

- **Commission defaults** - `defaultCommissionRate`, `defaultCommissionType`
- **Cookie/tracking** - `cookieDuration`, `cookieName`, `attributionModel`, `referralParamName`
- **Features** - `enableCouponTracking`, `enableLifetimeCommissions`, `enableMultiTier`, `enableFraudDetection`
- **Approval** - `autoApproveAffiliates`, `autoApproveReferrals`, `holdPeriodDays`
- **Payouts** - `minimumPayoutAmount`, `batchAutoProcessEnabled`, `batchAutoProcessCadence`, `requirePayoutVerification`, `defaultPayoutVerifierId`, `notifyVerifierOnRequest`
- **Fraud thresholds** - `fraudClickVelocityThreshold`, `fraudClickVelocityWindow`, `fraudRapidConversionMinutes`, `fraudIpReuseThreshold`, `fraudAutoFlag`
- **Coupons** - `enableCouponCreation`, `maxCouponsPerAffiliate`, `allowAffiliateSelfServiceCoupons`, `maxSelfServiceDiscountPercent`
- **Tracking** - `clickRetentionDays`
- **Gateway credentials** - `paypalClientId`, `paypalClientSecret`, `paypalSandbox`, `paypalWebhookId`, `stripeSecretKey`, `stripeWebhookSecret`
