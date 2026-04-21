# Routes

All routes are registered in `KickBack::registerCpRoutes()` and
`KickBack::registerSiteRoutes()`.

## CP routes

Prefix: `kickback/`. Every action additionally enforces its own permission
check in its controller's `beforeAction()` - see
[permissions.md](permissions.md).

### Dashboard

| URL | Action |
|---|---|
| `kickback` | `DashboardController::actionIndex` |
| `kickback/dashboard` | `DashboardController::actionIndex` |

### Affiliates

| URL | Action |
|---|---|
| `kickback/affiliates` | `AffiliatesController::actionIndex` |
| `kickback/affiliates/<affiliateId:\d+>` | `AffiliatesController::actionEdit` |
| `kickback/affiliates/export` | `AffiliatesController::actionExport` |

Mutation actions (`save`, `approve`, `reject`, `suspend`, `reactivate`) are
POST-only and routed via `actionInput()` in templates, not via URL rules.

### Referrals

| URL | Action |
|---|---|
| `kickback/referrals` | `ReferralsController::actionIndex` |
| `kickback/referrals/export` | `ReferralsController::actionExport` |

`actionExport` streams a CSV with these columns, in order: `ID`, `Affiliate`,
`Program ID`, `Order ID`, `Customer Email`, `Order Subtotal`, `Status`,
`Attribution Method`, `Coupon Code`, `Date Created`, `Date Approved`.

### Commissions

| URL | Action |
|---|---|
| `kickback/commissions` | `CommissionsController::actionIndex` |
| `kickback/commissions/export` | `CommissionsController::actionExport` |

`actionExport` streams a CSV with these columns, in order: `ID`, `Affiliate`,
`Referral ID`, `Amount`, `Currency`, `Rate`, `Rate Type`, `Rule Applied`,
`Tier`, `Status`, `Date Created`, `Date Approved`, `Date Reversed`.

### Commission rules

| URL | Action |
|---|---|
| `kickback/commission-rules` | `CommissionRulesController::actionIndex` |
| `kickback/commission-rules/new` | `CommissionRulesController::actionEdit` |
| `kickback/commission-rules/<ruleId:\d+>` | `CommissionRulesController::actionEdit` |
| `kickback/commission-rules/export` | `CommissionRulesController::actionExport` |

### Affiliate groups

| URL | Action |
|---|---|
| `kickback/affiliate-groups` | `AffiliateGroupsController::actionIndex` |
| `kickback/affiliate-groups/new` | `AffiliateGroupsController::actionEdit` |
| `kickback/affiliate-groups/<groupId:\d+>` | `AffiliateGroupsController::actionEdit` |
| `kickback/affiliate-groups/export` | `AffiliateGroupsController::actionExport` |

### Payouts

| URL | Action |
|---|---|
| `kickback/payouts` | `PayoutsController::actionIndex` |
| `kickback/payouts/batch` | `PayoutsController::actionBatch` |
| `kickback/payouts/export` | `PayoutsController::actionExport` |
| `kickback/payouts/<payoutId:\d+>` | `PayoutsController::actionView` |

All export endpoints stream row-by-row through `CsvExportHelper` (500-row
chunks via `php://output`), so memory usage is constant regardless of dataset
size.

### Approvals (payout verification queue)

| URL | Action |
|---|---|
| `kickback/approvals` | `ApprovalsController::actionIndex` |
| `kickback/approvals/approve` | `ApprovalsController::actionApprove` |
| `kickback/approvals/reject` | `ApprovalsController::actionReject` |

Visible only when `Settings::$requirePayoutVerification` is true. Gated on
`PERMISSION_VERIFY_PAYOUTS`.

### Fraud

| URL | Action |
|---|---|
| `kickback/fraud` | `FraudController::actionIndex` |
| `kickback/fraud/<referralId:\d+>` | `FraudController::actionView` |
| `kickback/fraud/export` | `FraudController::actionExport` |

### Programs

| URL | Action |
|---|---|
| `kickback/programs` | `ProgramsController::actionIndex` |
| `kickback/programs/new` | `ProgramsController::actionEdit` |
| `kickback/programs/<programId:\d+>` | `ProgramsController::actionEdit` |
| `kickback/programs/export` | `ProgramsController::actionExport` |

### Reports

| URL | Action |
|---|---|
| `kickback/reports` | `ReportsController::actionIndex` |
| `kickback/reports/export` | `ReportsController::actionExport` |

### Settings

| URL | Action |
|---|---|
| `kickback/settings` | `SettingsController::actionIndex` |

## Site routes

### Referral tracking

| URL | Action | Notes |
|---|---|---|
| `r/<code:[a-zA-Z0-9_-]+>` | `TrackController::actionTrack` | Pretty URL; records click, sets cookie, redirects to `?url=` (validated against site host) or site home |
| *(any site GET)* | `handleSiteReferralParam` | Silent capture of `?ref=CODE` on any site GET request. Records click + sets cookie if the code matches an active affiliate. Configurable via `Settings::$referralParamName` (default `ref`). |

### Affiliate portal

Portal routes register **per-site**, only when
`Settings::getCurrentSitePortalPath()` returns a non-null path for the current
request's site. That method returns `null` unless the site handle appears in
`Settings::$affiliatePortalEnabledSites` AND has an entry in
`Settings::$affiliatePortalPaths`. See [configuration.md](configuration.md).

`{portalPath}` below is the value configured per site (e.g. `affiliate`,
`partner`, `partners/program`).

| URL | Action |
|---|---|
| `{portalPath}` | `PortalController::actionDashboard` |
| `{portalPath}/links` | `PortalController::actionLinks` |
| `{portalPath}/referrals` | `PortalController::actionReferrals` |
| `{portalPath}/commissions` | `PortalController::actionCommissions` |
| `{portalPath}/coupons` | `PortalController::actionCoupons` |
| `{portalPath}/settings` | `PortalController::actionSettings` |
| `{portalPath}/stripe-onboard` | `PortalController::actionStripeOnboard` |
| `{portalPath}/pending` | `PortalController::actionPending` |
| `{portalPath}/register` | `RegistrationController::actionForm` |

Portal mutations (`generate-coupon`, `save-settings`, `request-payout`) are
POST-only; the front-end templates post with `actionInput('kickback/portal/
{action}')` rather than hitting a URL rule.

## Console commands

Console controllers live in `src/console/controllers/` and are resolved via
the namespace swap in `KickBack::init()` (see [bootstrap.md](bootstrap.md)).
Invoke them with `php craft <command>` from the Craft project root.

### Overview

| Command | Purpose | Dev-mode only |
|---|---|---|
| `kickback/health/check` | Self-test of install state, gateways, verifier config, and stuck payouts | no |
| `kickback/payouts/auto-run` | Cron entry point for scheduled batch payouts | no |
| `kickback/reconcile/run` | Poll gateways for status drift on completed payouts | no |
| `kickback/coupons/bulk-generate` | Generate N coupons for one affiliate | no |
| `kickback/balances/recompute` | Reconcile `pendingBalance` / `lifetimeEarnings` against DB truth | no |
| `kickback/fraud/reevaluate` | Re-run fraud checks against existing referrals | no |
| `kickback/email/preview` | Send sample emails for template QA | no |
| `kickback/email/list` | List all email template types | no |
| `kickback/seed` | Populate realistic test data across all entities | **yes** |
| `kickback/seed/resave-payouts` | Re-save all payouts to sync element indexes | **yes** |
| `kickback/simulate/run` | Generate rule-mix traffic for resolver validation | **yes** |
| `kickback/verify/all` and `kickback/verify/<scenario>` | End-to-end scenario suite (12 scenarios) | **yes** |

Commands marked **dev-mode only** refuse to run unless
`Craft::$app->getConfig()->getGeneral()->devMode` is true.

### `kickback/health/check`

Runs five checks and exits non-zero on any failure:

1. Craft Commerce is installed
2. A default program exists
3. At least one payout gateway is configured
4. If `requirePayoutVerification` is on, `defaultPayoutVerifierId` is set
5. No payouts are stuck in `processing` for more than 24 hours

Designed for deploy-pipeline gates. No options.

### `kickback/payouts/auto-run`

Cron entry point for the scheduled batch-payout runner. Supports
`--dry-run` to simulate without touching the queue or last-run timestamp.
Full setup and cadence semantics live in [cron-setup.md](cron-setup.md).

### `kickback/reconcile/run`

Walks recent completed payouts and asks each gateway for the current status,
flipping locally to `reversed` when the gateway reports drift. See
[gateways.md#reconciliation](gateways.md#reconciliation) for the full flow.

| Option | Required | Default | Description |
|---|---|---|---|
| `--days` | no | `7` | Lookback window; values below 1 exit `USAGE` |

### `kickback/coupons/bulk-generate`

Generates N coupons for a single affiliate in one transaction, backed by
`CouponService::bulkCreateAffiliateCoupons()` (see
[services.md](services.md#couponservice-plugin-coupons)).

| Option | Required | Default | Description |
|---|---|---|---|
| `--prefix` | yes | - | Code prefix (e.g. `LAUNCH`) |
| `--count` | yes | - | Number of coupons to generate (capped at 1000) |
| `--affiliate` | yes | - | Affiliate element ID |
| `--discount` | no | `10.0` | Percentage discount, 0–100 |
| `--maxUses` | no | `0` | Per-coupon usage cap; `0` = unlimited |
| `--dryRun` | no | `0` | Print the first and last code that would be created without writing |

```bash
php craft kickback/coupons/bulk-generate --prefix=LAUNCH --count=100 --affiliate=42 --discount=10
```

Exit codes: `USAGE` (missing required option), `DATAERR` (affiliate not
found), `SOFTWARE` (service threw), `OK` (success).

### `kickback/balances/recompute`

Recalculates `pendingBalance` (sum of approved-unpaid commissions) and
`lifetimeEarnings` (sum of completed payouts) from source rows, and prints
any mismatches. Dry-run by default.

| Option | Required | Default | Description |
|---|---|---|---|
| `--dryRun` | no | `1` | Set to `0` to write corrections back to affiliates |
| `--affiliateId` | no | `null` | Limit to a single affiliate |

Useful after migrations, manual DB surgery, or as a monthly audit job.

### `kickback/fraud/reevaluate`

Re-runs `FraudService::evaluateReferral()` across existing referrals, e.g.
after tuning thresholds in Settings. Does not rescue already-approved
referrals; only flags new hits.

| Option | Required | Default | Description |
|---|---|---|---|
| `--days` | no | `null` | Only re-evaluate referrals newer than N days |
| `--dryRun` | no | `0` | When `1`, prints what would be flagged without writing |

### `kickback/email/preview`

Sends sample renders of all four notification templates to an inbox of your
choice, using fixed fixture data. Use this to verify template overrides in
`templates/_kickback/emails/` before deploying.

| Option | Required | Default | Description |
|---|---|---|---|
| `--to` | yes | - | Recipient email address |
| `--type` | no | `all` | One of `approval`, `rejection`, `payout`, `fraud-alert`, or `all` |

### `kickback/email/list`

Prints the four available template types and the override path convention.
No options.

### `kickback/seed`

Dev-mode only. Seeds realistic demo data: programs, affiliate groups,
affiliates (one per status), commission rules across all rule types, clicks,
referrals, commissions (covering the status lifecycle), payouts, coupons,
and customer links. Backing test users are created under
`*.affiliate@test.example.com`.

| Option | Required | Default | Description |
|---|---|---|---|
| `--fresh` | no | `0` | When `1`, clear existing Kickback data before seeding |

Run `php craft kickback/seed/resave-payouts` after direct DB writes (e.g. a
restored backup) to resync payout element indexes.

### `kickback/simulate/run`

Dev-mode only. Generates synthetic referrals + commissions against existing
active affiliates to stress-test commission-rule resolution. Requires seed
data to exist.

| Option | Required | Default | Description |
|---|---|---|---|
| `--count` | no | `200` | Number of synthetic referrals to generate |
| `--seed` | no | `42` | Random seed for reproducibility |
| `--dryRun` | no | `1` | Set to `0` to persist results |
| `--report` | no | `null` | Path to write a JSON summary |
| `--mix` | no | `product:25,category:20,tiered:20,bonus:15,group:10,program:10` | Weighted rule-type mix |

### `kickback/verify/*`

Dev-mode only. End-to-end scenario suite - each scenario seeds its own
fixtures under a `verify-<scenario>` handle prefix, exercises a real service
path, and asserts expected state. Scenarios:

`percentage`, `flat`, `tiered`, `group`, `bonus`, `mlm`, `coupon`, `refund`,
`payout`, `self-referral`, `fraud`, `create-commission`.

Run all with `kickback/verify/all`, or one with e.g. `kickback/verify/tiered`.
Fixture data is cleaned up on exit by default.

| Option | Required | Default | Description |
|---|---|---|---|
| `--keep` | no | `0` | Leave fixture data behind for inspection |
| `--only` | no | `null` | With `verify/all`, run only the named scenario |
