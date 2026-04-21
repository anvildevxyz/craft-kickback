# Bootstrap

How the plugin wires itself up. All of this happens in `src/KickBack.php`.

## `KickBack::config()`

Registers 15 services as plugin components. Access them via
`KickBack::getInstance()->{handle}`:

| Handle | Class | Purpose |
|---|---|---|
| `affiliates` | `AffiliateService` | Affiliate lifecycle, balances |
| `affiliateGroups` | `AffiliateGroupService` | Groups + group-level rates |
| `programs` | `ProgramService` | Program CRUD, default program seeding |
| `tracking` | `TrackingService` | Click recording, cookies, code lookup |
| `referrals` | `ReferralService` | Order attribution, refund/cancel handling |
| `commissions` | `CommissionService` | Commission calc, approval, reversal |
| `commissionRules` | `CommissionRuleService` | Product/category/MLM rule lookup |
| `coupons` | `CouponService` | Commerce-discount-backed coupons |
| `fraud` | `FraudService` | Fraud detection + flagging |
| `payouts` | `PayoutService` | Payout creation + processing |
| `payoutGateways` | `PayoutGatewayService` | Gateway registry |
| `notifications` | `NotificationService` | Transactional email |
| `reporting` | `ReportingService` | Aggregated stats for dashboards |
| `approvals` | `ApprovalService` | Four-eyes review queue (payouts today) |

## `KickBack::init()` - registration order

Order matters: later hooks assume earlier ones ran.

1. **Alias** - `@kickback` points at the plugin base path.
2. **Console namespace swap** - if the request is a console request, the
   controller namespace is switched to `console\controllers` so `craft
   kickback/...` commands resolve to the right classes.
3. **Site template roots** - registers the plugin's `templates/` dir as a site
   template root under the plugin id, so front-end controllers (portal,
   registration) can render `kickback/portal/...` etc. The base Plugin class
   only wires CP roots.
4. **Element types** - 7 elements: `AffiliateElement`, `AffiliateGroupElement`,
   `ProgramElement`, `CommissionRuleElement`,
   `PayoutElement`, `ReferralElement`, `CommissionElement`.
5. **Twig variable** - `craft.kickback` is bound to `KickbackVariable`.
6. **CP routes** - see [routes.md](routes.md).
7. **Site routes** - see [routes.md](routes.md). Portal routes are per-site and
   only register when `Settings::getCurrentSitePortalPath()` returns a path for
   the current request's site.
8. **Permissions** - 12 permissions, some nested; see
   [permissions.md](permissions.md).
9. **Garbage collection** - on Craft's `Gc::EVENT_RUN`, deletes partial records
   for all 7 element tables AND, if `autoApproveReferrals` is on and
   `holdPeriodDays > 0`, pushes `ApproveHeldReferralsJob` into the queue. This
   is how held referrals get promoted to approved - GC is the heartbeat.
10. **Commerce listeners** - 3 hooks, all guarded on `class_exists()` so the
    plugin installs cleanly without Commerce:
    - `Order::EVENT_AFTER_COMPLETE_ORDER` → `referrals->processOrder()`
    - `Payments::EVENT_AFTER_REFUND_TRANSACTION` → `referrals->handleRefund()`
    - `OrderHistories::EVENT_ORDER_STATUS_CHANGE` →
      `referrals->handleOrderStatusChange()`
11. **Notification listeners** - 4 subscriptions wiring service events to
    `NotificationService`:
    - Affiliate approved / rejected → email affiliate
    - Payout processed → email affiliate
    - Fraud flagged → email admin
12. **Approval targets** - `approvals->registerTarget()` is called for the
    three built-in target types: `payout`, `affiliate`, `commission`.
    Third-party modules can register additional types from their own `init()`
    by implementing `ApprovalTargetInterface`; see
    [services.md#approvalservice](services.md#approvalservice-plugin-approvals).
13. **`onInit` deferred hook** - once the app is fully initialized, calls
    `handleSiteReferralParam()`, which silently captures `?ref=CODE` on any
    site GET request, validates the code, and records a click via
    `TrackingService`. This is the "invisible" entry point - users never hit
    `/r/<code>` for query-param landings.

## `afterInstall()`

Runs `programs->createDefaultProgram()`, seeding a default program at 10% so
new installs are immediately usable without manual setup.

## CP nav

`getCpNavItem()` builds the subnav from a gated list. Dashboard is always
visible; each other entry is only added if the current user holds the
corresponding permission. Nav keys: dashboard, affiliates, referrals, fraud,
commissions, commission-rules, affiliate-groups, payouts, approvals, programs,
reports, settings.

## Settings

`createSettingsModel()` returns a `Settings` model; `settingsHtml()` renders
`kickback/settings/index`. Full field reference in
[configuration.md](configuration.md).
