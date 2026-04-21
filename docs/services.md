# Services API Reference

All services are accessed via the plugin instance:

```php
$plugin = \anvildev\craftkickback\KickBack::getInstance();
```

## AffiliateService (`$plugin->affiliates`)

Handles affiliate registration, status transitions, and balance management.

### Event Constants

```php
AffiliateService::EVENT_BEFORE_APPROVE_AFFILIATE
AffiliateService::EVENT_AFTER_APPROVE_AFFILIATE
AffiliateService::EVENT_BEFORE_REJECT_AFFILIATE
AffiliateService::EVENT_AFTER_REJECT_AFFILIATE
AffiliateService::EVENT_BEFORE_SUSPEND_AFFILIATE
AffiliateService::EVENT_AFTER_SUSPEND_AFFILIATE
AffiliateService::EVENT_BEFORE_REACTIVATE_AFFILIATE
AffiliateService::EVENT_AFTER_REACTIVATE_AFFILIATE
```

### Methods

```php
// Lookups
getAffiliateById(int $id): ?AffiliateElement
getAffiliateByReferralCode(string $code): ?AffiliateElement
getAffiliateByUserId(int $userId): ?AffiliateElement
getAffiliatesByIds(int[] $ids): array<int, AffiliateElement>
isAffiliate(int $userId): bool

// Registration
registerAffiliate(User $user, int $programId, array $attributes = []): ?AffiliateElement

// Status transitions (all fire before/after events)
approveAffiliate(AffiliateElement $affiliate): bool
rejectAffiliate(AffiliateElement $affiliate): bool
suspendAffiliate(AffiliateElement $affiliate): bool
reactivateAffiliate(AffiliateElement $affiliate): bool

// Balance management
addPendingBalance(AffiliateElement $affiliate, float $amount): bool
deductPendingBalance(AffiliateElement $affiliate, float $amount): bool
recordPayout(AffiliateElement $affiliate, float $amount): bool
incrementReferralCount(AffiliateElement $affiliate): bool

// Utilities
generateReferralCode(User $user): string
```

### Usage Example

```php
use anvildev\craftkickback\KickBack;

$plugin = KickBack::getInstance();
$user = Craft::$app->getUsers()->getUserById(42);

// Register a new affiliate
$affiliate = $plugin->affiliates->registerAffiliate($user, $programId, [
    'paypalEmail' => $user->email,
    'payoutMethod' => 'paypal',
]);

// Approve them
if ($affiliate) {
    $plugin->affiliates->approveAffiliate($affiliate);
}
```

---

## ReferralService (`$plugin->referrals`)

Processes order attribution, manages referral lifecycle, and handles refunds and cancellations.

### Event Constants

```php
ReferralService::EVENT_BEFORE_CREATE_REFERRAL
ReferralService::EVENT_AFTER_CREATE_REFERRAL
ReferralService::EVENT_BEFORE_APPROVE_REFERRAL
ReferralService::EVENT_AFTER_APPROVE_REFERRAL
ReferralService::EVENT_BEFORE_REJECT_REFERRAL
ReferralService::EVENT_AFTER_REJECT_REFERRAL
```

### Methods

```php
// Order processing (called automatically on order completion)
processOrder(Order $order): ?ReferralRecord
createReferral(AffiliateElement $affiliate, Order $order, float $orderSubtotal, ?int $clickId, string $method, ?string $couponCode, ?array $referralResolutionTrace = null): ?ReferralRecord

// Lookups
getReferralById(int $id): ?ReferralRecord
getReferralsByAffiliateId(int $affiliateId, ?string $status, ?int $limit, ?int $offset): ReferralRecord[]
countReferralsByAffiliateId(int $affiliateId, ?string $status = null, ?string $subId = null): int
orderHasReferral(int $orderId): bool

// Status transitions
approveReferral(ReferralRecord $referral): bool
rejectReferral(ReferralRecord $referral): bool

// Commerce event handlers (called automatically)
handleRefund(Transaction $refundTransaction): void
handleOrderStatusChange(Order $order): void
```

---

## CommissionService (`$plugin->commissions`)

Handles commission creation, approval, rejection, reversal, and rate resolution.

### Event Constants

```php
CommissionService::EVENT_BEFORE_APPROVE_COMMISSION
CommissionService::EVENT_AFTER_APPROVE_COMMISSION
CommissionService::EVENT_BEFORE_REJECT_COMMISSION
CommissionService::EVENT_AFTER_REJECT_COMMISSION
CommissionService::EVENT_BEFORE_REVERSE_COMMISSION
CommissionService::EVENT_AFTER_REVERSE_COMMISSION
```

### Methods

```php
// Creation
createCommission(ReferralRecord $referral, AffiliateElement $affiliate, Order $order, ?string $currency = null, float $splitFactor = 1.0): ?CommissionRecord
createMultiTierCommissions(ReferralRecord $referral, AffiliateElement $affiliate, float $orderSubtotal, ?string $currency = null): void

// Status transitions
approveCommission(CommissionRecord $commission): bool
rejectCommission(CommissionRecord $commission): bool
reverseCommission(CommissionRecord $commission): bool
reverseCommissionsProportionally(CommissionRecord[] $commissions, float $refundRatio): void

// Lookups
getCommissionById(int $id): ?CommissionRecord
getCommissionsByReferralId(int $referralId): CommissionRecord[]
getCommissionsByAffiliateId(int $affiliateId, ?string $status, ?int $limit, ?int $offset): CommissionRecord[]
countCommissionsByAffiliateId(int $affiliateId, ?string $status): int

// Calculation
calculateAmount(float $orderSubtotal, float $rate, string $rateType): float
```

### Commission Rate Resolution

When `createCommission()` is called, the rate is resolved through a 6-level priority chain (see [Architecture > Commission Rate Resolution](architecture.md#commission-rate-resolution)).

---

## CommissionRuleService (`$plugin->commissionRules`)

Manages commission rules for products, categories, and MLM tiers.

### Methods

```php
// Lookups
getRuleById(int $id): ?CommissionRule
getRulesByProgramId(int $programId): CommissionRule[]
getRulesByType(int $programId, string $type): CommissionRule[]

// Rule matching
findProductRule(int $programId, int $productId): ?CommissionRule
findCategoryRule(int $programId, int $categoryId): ?CommissionRule
findMlmTierRule(int $programId, int $tierLevel): ?CommissionRule

// CRUD
saveRule(CommissionRule $rule): bool
deleteRuleById(int $id): bool
```

---

## FraudService (`$plugin->fraud`)

Detects and manages fraudulent referral activity.

### Event Constants

```php
FraudService::EVENT_AFTER_FLAG_REFERRAL
FraudService::EVENT_AFTER_APPROVE_FLAGGED
FraudService::EVENT_AFTER_REJECT_FLAGGED
```

### Methods

```php
// Detection
evaluateReferral(ReferralRecord $referral): string[]  // Returns fraud flags (empty = clean)

// Management
flagReferral(ReferralRecord $referral, string[] $flags): bool
approveFlaggedReferral(ReferralRecord $referral): bool  // Approves associated commissions too
rejectFlaggedReferral(ReferralRecord $referral): bool   // Rejects associated commissions too

// Lookups
getFlaggedReferrals(): ReferralRecord[]
getFraudStats(): array{flagged: int, totalBlocked: int, recentFlags: ReferralRecord[]}
```

### Fraud Checks

The `evaluateReferral()` method runs five checks:

1. **Click velocity** - Too many clicks from the same IP in the configured window
2. **Suspicious user agent** - Bot-like user agents (curl, selenium, etc.)
3. **Rapid conversions** - Multiple conversions from the same affiliate in a short time
4. **Duplicate customer** - Same customer email already converted for this affiliate
5. **IP reuse** - Same IP used across 5+ different affiliates in 24 hours

---

## PayoutService (`$plugin->payouts`)

Handles payout creation, processing, and batch disbursement to affiliates.

### Event Constants

```php
PayoutService::EVENT_BEFORE_CREATE_PAYOUT
PayoutService::EVENT_AFTER_CREATE_PAYOUT
PayoutService::EVENT_BEFORE_PROCESS_PAYOUT
PayoutService::EVENT_AFTER_PROCESS_PAYOUT
```

### Methods

```php
// Creation
createPayout(AffiliateElement $affiliate, ?string $notes = null): ?PayoutElement
createBatchPayouts(?string $notes = null): PayoutElement[]

// Processing
processPayout(PayoutElement $payout): bool
processBatchViaGateways(PayoutElement[] $payouts): array<int, bool>  // Keyed by payout ID
completePayout(PayoutElement $payout, ?string $transactionId): bool
failPayout(PayoutElement $payout, ?string $notes): bool
cancelPayout(PayoutElement $payout): bool

// Lookups
getPayoutById(int $id): ?PayoutElement
getPayoutsByAffiliateId(int $affiliateId): PayoutElement[]
getAllPayouts(?string $status = null): PayoutElement[]
getEligibleAffiliates(): AffiliateElement[]
```

---

## PayoutGatewayService (`$plugin->payoutGateways`)

Registers and provides access to payout payment gateways.

### Methods

```php
getGateway(string $handle): ?PayoutGatewayInterface     // e.g. 'paypal', 'stripe'
getConfiguredGateways(): PayoutGatewayInterface[]        // Only gateways with valid API keys
getStripeGateway(): ?StripeGateway                       // Typed accessor for Stripe-specific methods
```

---

## AffiliateGroupService (`$plugin->affiliateGroups`)

Manages affiliate groups and their commission rate overrides. Groups provide a
commission rate fallback in the resolution chain - when an affiliate belongs to
a group and no higher-priority rule (affiliate override, bonus, tiered) matches,
the group's rate is used.

### Methods

```php
getGroupById(int $id): ?AffiliateGroupElement    // Used by CommissionService::resolveBaseRate() during rate resolution
getGroupByHandle(string $handle): ?AffiliateGroupElement
getAllGroups(): AffiliateGroupElement[]            // Ordered by sortOrder ASC, then title ASC
deleteGroupById(int $id): bool
```

`getAllGroups()` respects the `sortOrder` property - use it when building
dropdowns, frontend group listings, or anywhere display order matters.

---

## ApprovalService (`$plugin->approvals`)

Handles the approval lifecycle for polymorphic targets (payouts, affiliates, commissions). Enforces the four-eyes rule (self-verification guard) and the PENDING-only state machine. Target types are registered at boot via `registerTarget()`.

### Event Constants

```php
ApprovalService::EVENT_AFTER_REQUEST   // After an approval row is created
ApprovalService::EVENT_AFTER_APPROVE   // After an approval is resolved as approved
ApprovalService::EVENT_AFTER_REJECT    // After an approval is resolved as rejected
```

These events fire a plain `yii\base\Event` (no custom payload). State-change side-effects - such as updating the target's own status - are handled by the target's `ApprovalTargetInterface::onReject()` hook, not via these events.

### Methods

```php
// Target registration
registerTarget(string $targetType, string $handlerClass): void
getTargetHandler(string $targetType): ApprovalTargetInterface

// Lifecycle
request(string $targetType, int $targetId, ?int $requestedUserId = null): ApprovalRecord
approve(int $approvalId, int $resolverUserId, ?string $note = null): ApprovalRecord
reject(int $approvalId, int $resolverUserId, ?string $note): ApprovalRecord

// Lookups
getFor(string $targetType, int $targetId): ?ApprovalRecord

// Cleanup
deleteFor(string $targetType, int $targetId): int

// Static guard methods (unit-testable without DB)
static checkSelfVerify(int $resolverId, ?int $creatorId): void
static checkResolvable(int $approvalId, string $currentStatus): void
static requireNonEmptyRejectionNote(?string $note): string
```

### Target Registration

`registerTarget()` must be called before any `request()`, `approve()`, or `reject()` call for the given type. The plugin registers the three built-in types in `KickBack::init()`:

```php
use anvildev\craftkickback\services\approvals\PayoutApprovalTarget;
use anvildev\craftkickback\services\approvals\AffiliateApprovalTarget;
use anvildev\craftkickback\services\approvals\CommissionApprovalTarget;

// Built-in registrations happen in KickBack::init():
$this->approvals->registerTarget('payout', PayoutApprovalTarget::class);
$this->approvals->registerTarget('affiliate', AffiliateApprovalTarget::class);
$this->approvals->registerTarget('commission', CommissionApprovalTarget::class);
```

Third-party modules can register additional target types by implementing `ApprovalTargetInterface` and calling `registerTarget()` from their own `init()`.

---

## CouponService (`$plugin->coupons`)

Manages affiliate coupon codes backed by Commerce discounts. Each Kickback coupon record links an affiliate to a Commerce `Discount` + `Coupon` pair; deleting a Kickback coupon cascades to the Commerce side.

### Methods

```php
// Creation
createAffiliateCoupon(AffiliateElement $affiliate, string $code, float $discountPercent, int $maxUses = 0): ?CouponRecord
bulkCreateAffiliateCoupons(AffiliateElement $affiliate, string $prefix, int $count, float $discountPercent, int $maxUses = 0): CouponRecord[]

// Lookups
getCouponsByAffiliateId(int $affiliateId): CouponRecord[]

// Deletion
deleteCoupon(int $couponId): bool

// Code generation
generateCouponCode(AffiliateElement $affiliate): string
static buildBulkCodes(string $prefix, int $count): string[]
```

`generateCouponCode()` builds a code from the affiliate's referral code plus a CSPRNG-backed suffix using `bin2hex(random_bytes(2))`. A collision-avoidance loop via `UniqueCodeHelper::generate()` ensures uniqueness against the `kickback_coupons` table.

`bulkCreateAffiliateCoupons()` generates `$count` coupons for a single affiliate in one DB transaction, rolling the whole batch back on any failure. Codes follow a `PREFIX###` zero-padded serial pattern produced by the static `buildBulkCodes()` helper - pad width is `max(3, strlen((string)$count))`, so `count=5` yields `LAUNCH001..LAUNCH005` and `count=1000` yields `LAUNCH0001..LAUNCH1000`. Before writing, the method runs a pre-flight check against `kickback_coupons` and throws `\RuntimeException` listing the colliding codes if any already exist. Defensive bounds throw `\InvalidArgumentException`: `count` is capped at 1000 per batch, `discountPercent` must be between 0 and 100, and `maxUses` must be zero or positive. The CP affiliate edit page exposes a "Bulk Generate Coupons" pane that posts to `kickback/affiliates/bulk-generate-coupons` and is backed by this same method; the `kickback/coupons/bulk-generate` console command (see [routes.md](routes.md#console-commands)) is the CLI entry point.

---

## EmailRenderService (`$plugin->emailRender`)

Renders HTML email templates with user-override support.

### Template Resolution

1. `templates/_kickback/emails/{template}.twig` - user override in project
2. Plugin's `src/templates/emails/{template}.twig` - default

### Methods

```php
render(string $template, array $variables = []): string
```

`$template` is the template name without extension (e.g. `'approval'`). The `siteName` variable is injected automatically.

### Available Templates

| Template | Variables |
|----------|-----------|
| `approval` | `name`, `portalUrl`, `recruiterName` (nullable) |
| `rejection` | `name` |
| `payout` | `name`, `amount`, `method` |
| `fraud-alert` | `referralId`, `affiliateId`, `flags` |

All templates extend `_base.twig` which provides a clean HTML email layout with header, content block, and footer.

### Overriding Templates

Copy the plugin default from `vendor/anvildev/craft-kickback/src/templates/emails/{template}.twig` to `templates/_kickback/emails/{template}.twig` and customize. The base layout can also be overridden.

### Preview Command

```bash
php craft kickback/email/preview --to=you@example.com        # Send all 4
php craft kickback/email/preview --to=you@example.com --type=approval  # Send one
php craft kickback/email/list                                  # List types
```

---

## NotificationService (`$plugin->notifications`)

Sends email notifications for affiliate status changes, payout completions, and fraud alerts. All four public methods are Yii event handlers wired up during `KickBack::init()` - they are not intended to be called directly. Emails are rendered as HTML via `EmailRenderService`.

### Methods

```php
onAffiliateApproved(AffiliateEvent $event): void   // Emails affiliate: "Your application has been approved"
onAffiliateRejected(AffiliateEvent $event): void   // Emails affiliate: "Your application was not approved"
onPayoutCompleted(PayoutEvent $event): void        // Emails affiliate: payout amount + method
onReferralFlagged(FraudEvent $event): void         // Emails site admin: fraud alert with flag list
```

The admin address for fraud alerts is read from `App::mailSettings()->fromEmail` (Craft 5 project config). No separate notification-specific configuration is required.

---

## ProgramService (`$plugin->programs`)

Manages affiliate programs and their default commission settings. Programs are full Craft elements (`ProgramElement`) and support multisite translation for `name`, `description`, and `termsAndConditions`.

### Event Constants

```php
ProgramService::EVENT_BEFORE_SAVE_PROGRAM    // Before a program is saved (cancellable)
ProgramService::EVENT_AFTER_SAVE_PROGRAM     // After a program is saved
ProgramService::EVENT_BEFORE_DELETE_PROGRAM  // Before a program is deleted (cancellable)
ProgramService::EVENT_AFTER_DELETE_PROGRAM   // After a program is deleted
```

All four events carry a `ProgramEvent` with `$program` (the `ProgramElement`) and `$isNew` (bool).

### Methods

```php
// Lookups
getProgramById(int $id): ?ProgramElement
getProgramByHandle(string $handle): ?ProgramElement
getDefaultProgram(): ?ProgramElement     // First active program, ordered by creation date
getAllPrograms(): ProgramElement[]

// CRUD
saveProgram(ProgramElement $program): bool
deleteProgramById(int $id): bool         // Blocked if active affiliates exist in the program

// Install helper
createDefaultProgram(): ProgramElement
```

`deleteProgramById()` checks for active affiliates before firing the delete events; if any exist it logs a warning and returns `false` without touching the element.

---

## ReportingService (`$plugin->reporting`)

Provides reporting queries for stats, charts, and top affiliates. Suitable for use from controllers, Twig templates, and console commands.

### Methods

```php
// Date helpers
resolveDatePreset(string $preset): array   // Returns [?string $startDate, ?string $endDate]
                                           // Presets: 'thisMonth', 'lastMonth', 'last30', 'thisYear', 'allTime'

// Aggregates
getStats(?string $startDate = null, ?string $endDate = null): array
// Returns: {totalAffiliates, activeAffiliates, totalReferrals, approvedReferrals,
//           totalCommissions, approvedCommissions, pendingCommissions,
//           totalClicks, totalPayouts}

getTopAffiliates(int $limit = 10): AffiliateElement[]   // Ordered by lifetimeEarnings DESC

// Chart data
getDailyCommissions(?string $startDate = null, ?string $endDate = null): array  // [{date, total}]
getDailyReferrals(?string $startDate = null, ?string $endDate = null): array    // [{date, total}]

// Query helper
applyDateFilter(Query $query, ?string $startDate, ?string $endDate): Query
```

Date strings are `Y-m-d` format. When both `$startDate` and `$endDate` are `null`, no date filter is applied (equivalent to the `allTime` preset).

---

## TrackingService (`$plugin->tracking`)

Handles click tracking, referral cookie management, and affiliate attribution resolution.

### Methods

```php
// Click recording (single write path)
recordClick(AffiliateElement $affiliate, string $landingUrl): int  // Returns click ID

// Cookie management
setReferralCookie(string $referralCode, int $clickId, int $durationDays): void
getReferralCookie(): ?array  // Returns {code, clickId, timestamp} or null if missing/tampered
clearReferralCookie(): void

// Attribution resolution
resolveAffiliate(): ?array          // Returns {affiliate, clickId, method} or null
resolveAllAffiliates(): array       // Returns [{affiliate, clickId, method}] for linear attribution
resolveAffiliateFromCoupon(string $couponCode): ?AffiliateElement
```

`recordClick()` is the single write path for all click tracking. Both `TrackController::actionTrack` (explicit `?ref=` link clicks) and the silent referral-param capture in `KickBack::handleSiteReferralParam` (passive `?ref=` on any page load) route through this method. Extension code that needs to record a custom entry point should call `$plugin->tracking->recordClick()` rather than writing a `ClickRecord` directly.

The referral cookie is HMAC-signed via `Craft::$app->getSecurity()->hashData()`. Legacy unsigned cookies fail validation and are treated as absent.

Cookie duration is resolved per-program: `recordClick()` reads `$program->cookieDuration` and falls back to the global `Settings::$cookieDuration` when the program value is 0 or absent.
