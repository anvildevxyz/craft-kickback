# Architecture

Kickback is a Craft CMS 5 plugin that adds affiliate and referral marketing on
top of Craft Commerce. It follows Craft's plugin architecture - service
components, custom elements, ActiveRecord-backed migrations, and
event-driven extensibility.

For low-level detail (what `init()` actually does, full route table,
permission list, every settings property, how the flows work) see:

- [bootstrap.md](bootstrap.md)
- [routes.md](routes.md)
- [permissions.md](permissions.md)
- [configuration.md](configuration.md)
- [flows.md](flows.md)

## Directory layout

```
src/
├── KickBack.php              # Plugin class - boots services, routes, events
├── controllers/              # 16 CP + site controllers
├── elements/
│   ├── AffiliateElement.php  # Plus 6 more element types
│   └── db/                   # Element query classes
├── enums/                    # Typed enums (AttributionModel, etc.)
├── events/                   # Custom event classes
├── exceptions/               # Domain-specific exceptions
├── gateways/                 # PayPal / Stripe / Manual
├── helpers/                  # CsvExportHelper, UniqueCodeHelper
├── jobs/                     # BatchPayoutJob, ApproveHeldReferralsJob, ReconcilePayoutsJob
├── migrations/               # Schema migrations (Install.php + 18 incremental)
├── models/                   # Settings + domain value objects
├── records/                  # ActiveRecord classes (DB layer)
├── services/                 # 15 service components + approvals/ subdir
├── templates/                # CP + site-facing Twig
├── translations/             # i18n strings
└── variables/                # craft.kickback Twig variable
```

## Service layer

15 service components are registered in `KickBack::config()`. See
[bootstrap.md](bootstrap.md#kickbackconfig) for the full table. They split
roughly into:

- **Lifecycle:** `AffiliateService`, `AffiliateGroupService`, `ProgramService`
- **Attribution:** `TrackingService`, `ReferralService`
- **Money:** `CommissionService`, `CommissionRuleService`, `CouponService`,
  `PayoutService`, `PayoutGatewayService`
- **Risk:** `FraudService`
- **Platform:** `NotificationService`, `ReportingService`, `ApprovalService`

All business logic lives in services. Controllers are thin and delegate;
event handlers forward to services. No service holds request-specific state.

## Elements

7 element types:

| Element | Table | Role |
|---|---|---|
| `AffiliateElement` | `kickback_affiliates` | A person earning commissions |
| `AffiliateGroupElement` | `kickback_affiliate_groups` | Tier with its own rate |
| `ProgramElement` | `kickback_programs` | Commission program |
| `CommissionRuleElement` | `kickback_commission_rules` | Per-product/category rate override |
| `ReferralElement` | `kickback_referrals` | A tracked order attribution |
| `CommissionElement` | `kickback_commissions` | Computed commission from a referral |
| `PayoutElement` | `kickback_payouts` | A disbursement to an affiliate |

Elements follow Craft conventions: element queries, CP edit screens,
searchable/table attributes, permission callbacks, and partial-element GC.

### Multi-site translation for Programs

`ProgramElement` is localized (`isLocalized() === true`). Its columns split
into two tables:

- `kickback_programs` - **global** fields shared across sites: `handle`,
  `defaultCommissionRate`, `defaultCommissionType`, `cookieDuration`,
  `allowSelfReferral`, `enableCouponCreation`, `status`, `propagationMethod`.
- `kickback_programs_sites` - **per-site** translatable fields keyed by
  `(id, siteId)`: `name`, `description`, `termsAndConditions`.

`ProgramQuery::beforePrepare()` `leftJoin`s `kickback_programs_sites` on the
current site and selects the translatable columns directly - no primary-site
fallback. With the default propagation method (`all`), every supported site
always has its own row, so no COALESCE is needed.

**Propagation method.** How edits on one site flow to siblings is controlled
by the standard Craft `PropagationMethod` enum, exposed on the program edit
screen as a select:

- `none` - changes stay on the current site only.
- `all` - changes propagate to every site.
- `siteGroup` - changes propagate to sites in the same site group.
- `language` - changes propagate to sites with the same language.

The value lives in `kickback_programs.propagationMethod` and is read by the
shared `HasPropagation` trait (`src/traits/HasPropagation.php`), which
implements `getSupportedSites()` based on the chosen method. The trait uses a
private backing field plus `setPropagationMethod()` / `getPropagationMethod()`
accessors - Craft's `ElementQuery` populates rows via direct property
assignment, and a public typed enum property would `TypeError` when Craft
writes the raw string back into the element. The Yii `Component::__set` path
routes through the setter, where the string is coerced into
`PropagationMethod` via `tryFrom()`.

**`afterSave()` split.** The element's `afterSave()` only writes the global
`ProgramRecord` on the origin save (guarded by `!$this->propagating`), but
always writes the per-site `ProgramSiteRecord`. That way, when Craft runs
sibling-site passes for propagation methods other than `none`, the
translatable values actually get copied to each sibling row.

## Controllers

**CP:** `DashboardController`, `AffiliatesController`, `AffiliateGroupsController`,
`ReferralsController`, `CommissionsController`, `CommissionRulesController`,
`ProgramsController`, `FraudController`,
`PayoutsController`, `ApprovalsController`, `ReportsController`,
`SettingsController`.

**Site:** `TrackController` (referral link redirect), `PortalController`
(front-end affiliate self-service), `RegistrationController` (signup).

## Commerce integration

4 hooks, all guarded on `class_exists()` so the plugin installs cleanly
without Commerce:

| Commerce event | Handler | Purpose |
|---|---|---|
| `Order::EVENT_AFTER_COMPLETE_ORDER` | `ReferralService::processOrder` | Attribute order + create commission |
| `Payments::EVENT_AFTER_REFUND_TRANSACTION` | `ReferralService::handleRefund` | Reverse commissions proportionally |
| `OrderHistories::EVENT_ORDER_STATUS_CHANGE` | `ReferralService::handleOrderStatusChange` | Cancel referral on cancelled-status transitions |

## Commission rate resolution

When a commission is computed, the rate is resolved through a priority chain,
first match wins:

1. Affiliate-level override (`commissionRateOverride` /
   `commissionTypeOverride`)
2. Product-specific rule (matched by product ID)
3. Category-specific rule (matched by category ID)
4. Affiliate group rate
5. Program default (`defaultCommissionRate` / `defaultCommissionType`)
6. Global default (plugin settings)

See `CommissionService` for the resolver and [flows.md](flows.md) for how it
fits into the money path.

## Extension points

- **Events** - every service fires before/after events for its mutations
  (approve, reject, create, process, flag). See [events.md](events.md).
- **Gateways** - implement `PayoutGatewayInterface` and register via
  `PayoutGatewayService`. See [gateways.md](gateways.md).
- **Approval targets** - `ApprovalService::registerTarget('type', class)`
  makes a new resource reviewable in the approvals queue. Payouts are the
  only built-in target today.
- **Commission rules** - data-driven (CP-editable), not code-driven. Add new
  rule kinds by extending `CommissionRuleService` + the element type.
