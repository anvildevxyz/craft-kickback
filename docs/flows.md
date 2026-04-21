# Core flows

How money moves through the plugin, end to end. Each flow lists the entry
point, the services it touches, and the exit state.

## 1. Click → referral → commission → payout

The happy path.

```
visitor hits /r/CODE or ?ref=CODE
    │
    ▼
TrackingService::recordClick        (ClickRecord + sets _kb_ref cookie)
    │  cookie persists for cookieDuration days
    ▼
visitor places Commerce order
    │
    ▼  Order::EVENT_AFTER_COMPLETE_ORDER
ReferralService::processOrder       (reads cookie / coupon, picks affiliate by attributionModel)
    │  → ReferralElement created (status: pending)
    │  → referral cookie cleared (one conversion per click chain)
    ▼
CommissionService::createForReferral  (rate resolution via CommissionRuleService)
    │  → CommissionElement created (status: pending)
    ▼  … holdPeriodDays pass, Craft GC runs, ApproveHeldReferralsJob fires
ReferralService::approveReferral    (if autoApproveReferrals)
    │  → referral + commission flip to approved
    ▼
AffiliateService::addPendingBalance (affiliate.pendingBalance += commission.amount)
    │
    ▼  affiliate or admin triggers payout
PayoutService::createPayout         (row-locked; refuses if active payout exists)
    │  → PayoutElement created (status: pending)
    ▼  optionally held in approvals queue (see flow 4)
PayoutService::processPayout → gateway->processPayout (idempotent Stripe transfer / PayPal batch)
    │
    ▼  on success
PayoutService::completePayout       (status-conditioned UPDATE; deducts pendingBalance; marks commissions paid)
```

If the inbound URL includes `sub_id`, the value is persisted on `kickback_clicks.subId` by `TrackingService::recordClick()` and then denormalized onto `kickback_referrals.subId` when the click converts, so per-campaign reporting can filter referrals without joining the clicks table.

**Entry points:** `TrackController`, `handleSiteReferralParam`,
`Order::EVENT_AFTER_COMPLETE_ORDER` listener.
**Exit state:** commissions have `payoutId`, affiliate pendingBalance is
reduced, `lifetimeEarnings` is increased.

### Storefront display of used referral code

Checkout and confirmation templates can now display the code that was actually
used to attribute the order, with this fallback chain:

1. `order.couponCode` / `cart.couponCode` (coupon attribution)
2. Kickback referral row lookup by `orderId`:
   - `ReferralRecord.couponCode` if present
   - else the resolved affiliate's `referralCode`
3. During checkout only, current tracking cookie code (`_kb_ref`) via
   `craft.kickback.activeReferralCode()`

Template hooks in this repo:

- `templates/shop/checkout/_includes/order-summary.twig`
- `templates/shop/customer/order.twig`
- `templates/shop/_private/receipt/index.twig`

Twig helpers used:

- `craft.kickback.activeReferralCode()`
- `craft.kickback.referralCodeForOrder(order.id)`

### Attribution models

When a customer clicks multiple affiliate links before purchasing, the
`attributionModel` setting determines which affiliate(s) get credit.

**Last Click** (default) - the most recent affiliate wins. Each new referral
click overwrites the `_kb_ref` cookie. On checkout, only the last affiliate
in the cookie is resolved and credited.

```
visitor clicks Affiliate A link  →  cookie = A
visitor clicks Affiliate B link  →  cookie = B  (overwrites A)
visitor purchases
    → B gets 100% credit
```

**First Click** - the original affiliate wins. Once a cookie is set,
subsequent clicks from different affiliates are still recorded as
`ClickRecord` rows (for analytics), but the cookie is **not** overwritten.
The affiliate who introduced the customer keeps credit.

```
visitor clicks Affiliate A link  →  cookie = A
visitor clicks Affiliate B link  →  cookie = A  (preserved)
visitor purchases
    → A gets 100% credit
```

The guard is in `TrackingService::recordClick()` - when `attributionModel`
is `first_click` and a referral cookie already exists, the method returns
the click ID early without touching the cookie.

**Linear** - all affiliates who touched the customer share credit equally.
Instead of resolving a single affiliate from the cookie,
`ReferralService::processOrder()` calls
`TrackingService::resolveAllAffiliates()` to collect every affiliate in the
click chain. `processLinearAttribution()` then creates one referral and
commission per affiliate, each receiving an equal share of the total
commission.

```
visitor clicks Affiliate A link  →  cookie chain = [A]
visitor clicks Affiliate B link  →  cookie chain = [A, B]
visitor clicks Affiliate C link  →  cookie chain = [A, B, C]
visitor purchases (commission = $30)
    → A gets $10, B gets $10, C gets $10
```

The resolution order in `processOrder()` is: lifetime customer link →
coupon attribution → cookie-based attribution (last/first/linear). The
attribution model only governs the cookie-based step; coupon and lifetime
attribution always resolve to a single affiliate regardless of model.

## 2. Refund → commission reversal

```
Commerce refund transaction completes
    │  Payments::EVENT_AFTER_REFUND_TRANSACTION
    ▼
ReferralService::handleRefund
    │  computes refundRatio = refundAmount / orderTotal
    ▼
CommissionService reverses each commission for the order proportionally
    │  → commission.status = reversed (if already paid) or rejected (if still pending)
    ▼
AffiliateService::deductPendingBalance (only if still pending)
```

Refund ratio thresholds (`refundRatio = min(cumulativeRefunded / orderTotal, 1.0)`):

- `refundRatio >= 0.95`: treated as a full refund. Every commission is fully
  reversed and the referral is rejected.
- `refundRatio < 0.95`: treated as partial. `reverseCommissionsProportionally()`
  scales each commission down by `(1 - refundRatio)`; the affiliate's pending
  balance is adjusted to match.

Controlled by `Settings::$reverseCommissionOnRefund`. If false, the listener
is still invoked but the service logs and exits.

## 3. Order cancelled → referral cancelled

```
Order status changes to a handle in Settings::$cancelledStatusHandles
    │  OrderHistories::EVENT_ORDER_STATUS_CHANGE
    ▼
ReferralService::handleOrderStatusChange
    │  referral.status → cancelled
    ▼
pending commissions rejected; approved commissions reversed
```

Multiple cancelled-like handles are supported so sites with `cancelled`,
`refunded`, `void`, etc. can treat them uniformly.

## 4. Payout verification (four-eyes)

Opt-in via `Settings::$requirePayoutVerification`. When enabled:

```
PayoutService::createPayout
    │  after saving the PayoutElement, inside the same transaction
    ▼
ApprovalService::request('payout', $payout->id, defaultPayoutVerifierId)
    │  → ApprovalRecord (status: pending, assignee hint stamped)
    │  → email verifier if notifyVerifierOnRequest
    ▼
verifier opens /admin/kickback/approvals → reviews → approve or reject
    │
    ▼  on approve
ApprovalRecord.status = approved
    │
    ▼  later, process actor triggers processPayout
PayoutService::isVerifiedIfRequired()  ← checks approval row
    │  (returns false → processPayout bails)
    │  (returns true  → gateway transfer proceeds)
    ▼
Normal completePayout path
```

Without verification, `isVerifiedIfRequired()` short-circuits to `true` and
the normal path runs. The approval target registration
(`approvals->registerTarget('payout', ...)`) is what makes the approvals UI
treat a payout as a reviewable resource; additional targets can be registered
in [bootstrap.md](bootstrap.md#kickbackinit--registration-order) later.

## 5. Scheduled batch auto-processing

Driven by cron hitting `craft kickback/batch-payout/run` or similar on a
frequent schedule (every 15 min is fine - the cadence gate is the real
filter). See [cron-setup.md](cron-setup.md).

```
cron tick
    │
    ▼
PayoutService::shouldAutoRun(cadence, lastRun, now)
    │  false → exit silently (same calendar day OR cadence not due)
    │  true  → continue
    ▼
BatchPayoutJob queued
    │
    ▼
for each eligible affiliate (pendingBalance ≥ minimum, no active payout)
    PayoutService::createPayout
    PayoutService::processPayout  (if autoProcess was set on the job)
    │
    ▼
PayoutService::recordAutoRun      (stamps Settings::$batchAutoProcessLastRun)
```

Cadence logic is a pure function in `PayoutService::shouldAutoRun` -
testable, timezone-explicit, won't double-fire within one day.

## 6. Affiliate registration

```
visitor POSTs {portalPath}/register
    │
    ▼
RegistrationController::actionRegister
    │  honeypot check (field name: website)
    │  per-IP rate limit (10 / hour, sha1-keyed cache counter)
    │
    ▼ if visitor is anonymous
createUser()                        (enumeration-guarded - collisions return the same "check your email" UI as real pending signups)
    │
    ▼
AffiliateService::registerAffiliate (attaches to program, stamps parent code if provided and active)
    │
    ▼  depending on settings
    ├─ Craft user still pending email verification → "check your email" flash, no auto-login
    ├─ autoApproveAffiliates = false → redirect to /pending
    └─ autoApproveAffiliates = true  → login + redirect to portal dashboard
```

**Security notes:** the honeypot catches dumb bots; the rate limit is what
stops a targeted enumeration of the user table via Craft's verification email.
The enumeration guard uses the same response for "new" and "email already
taken" so an attacker can't probe the user table by watching for different
flashes.

## 7. Two-tier recruiting

Opt-in via `Settings::$enableMultiTier`. Surfaces the existing MLM commission
engine as a UX flow: an active affiliate recruits someone, and subsequent
referrals generate override rows for the recruiter's chain.

```
recruiter shares {portalPath}/register?recruiter=CODE
    │  URL copied from their /{portalPath}/team page
    ▼
visitor GETs the register form
    │
    ▼
RegistrationController::actionForm
    │  AffiliateService::getAffiliateByReferralCode(code)
    │  valid + active → pre-fill readonly parentReferralCode field
    │  invalid / inactive → non-blocking flash notice, field cleared
    ▼
visitor POSTs the form
    │
    ▼
RegistrationController::actionRegister
    │  re-validates parentReferralCode server-side
    │  AffiliateService::registerAffiliate stamps parentAffiliateId on the new row
    ▼  (normal registration flow from here - see flow 6)
    │
    ▼  later, on approval
NotificationService::onAffiliateApproved
    │  welcome email (approval.twig) mentions the recruiter by name when parentAffiliateId is set
    ▼
    … new affiliate generates referrals …
    ▼
CommissionService::createMultiTierCommissions
    │  walks parentAffiliateId chain up to Settings::$maxMlmDepth
    │  creates mlm_tier:N override CommissionElements for each ancestor
    ▼
recruiter opens {portalPath}/team
    │  sees their personal recruit URL (copy button) + downline list
    │  downline = affiliates whose parentAffiliateId = $this->_affiliate->id
```

The warn-on-invalid policy on the register page is deliberate: a bad recruiter
code shouldn't block signup, because the typical cause is a stale or
mistyped link - not abuse. The Team nav link and the `/team` route are both
gated on `enableMultiTier`; see [permissions.md](permissions.md#cp-nav-gating)
for the portal-side routing note.

## 8. Affiliate portal session

```
affiliate hits {portalPath}/...
    │
    ▼
PortalController::beforeAction
    │  requireLogin()
    │  lookup affiliate by userId
    │  null → redirect to /register
    │  pending → redirect to /pending (unless already there)
    │  suspended|rejected → ForbiddenHttpException
    │  active → continue
    ▼
action runs, always scoped to $this->_affiliate (never accepts an affiliateId param)
```

Every portal mutation - `generate-coupon`, `request-payout`, `save-settings`,
`stripe-onboard` - reads the affiliate from this cached identity, never from
the request body, which eliminates the IDOR risk class by construction.

## 9. Silent `?ref=` capture

This runs on every site GET, deferred until `Craft::$app->onInit` so all
services are wired.

```
any site GET request
    │
    ▼
handleSiteReferralParam()
    │  skip if CP, console, or non-GET
    │  skip if ?ref missing / not a string
    │  skip if code fails regex ^[a-zA-Z0-9_-]+$ or > 64 chars
    ▼
AffiliateService::getAffiliateByReferralCode
    │  skip if affiliate not active
    ▼
TrackingService::getReferralCookie
    │  skip if already set for this code (don't spam ClickRecord)
    ▼
sanitizeLandingUrl()                (http/https only, ≤2048 chars, valid URL)
    │
    ▼
TrackingService::recordClick
```

The landing URL sanitizer is defensive for the stored value - it's not a
redirect target, so the worst case of a malformed URL is a `fallbackPath`
being logged instead of the original.
