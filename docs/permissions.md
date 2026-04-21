# Permissions

12 permissions, some nested. Defined as constants on `KickBack` and registered
via `registerPermissions()`.

## Reference

| Constant | Handle | Tier | Purpose |
|---|---|---|---|
| `PERMISSION_MANAGE_AFFILIATES` | `kickback-manageAffiliates` | root | View / edit / create affiliates |
| `PERMISSION_APPROVE_AFFILIATES` | `kickback-approveAffiliates` | nested under manage | Approve, reject, suspend, reactivate affiliates; also required to write `affiliateStatus` or `commissionRateOverride` on the save form |
| `PERMISSION_MANAGE_REFERRALS` | `kickback-manageReferrals` | root | View / export referrals; also grants the Fraud Detection nav item |
| `PERMISSION_APPROVE_REFERRALS` | `kickback-approveReferrals` | nested under manage | Approve / reject referrals (including flagged-fraud review actions) |
| `PERMISSION_MANAGE_COMMISSIONS` | `kickback-manageCommissions` | root | View / export commissions + commission rules CRUD |
| `PERMISSION_APPROVE_COMMISSIONS` | `kickback-approveCommissions` | nested under manage | Approve / reject / reverse commissions |
| `PERMISSION_MANAGE_PAYOUTS` | `kickback-managePayouts` | root | View / export payouts |
| `PERMISSION_PROCESS_PAYOUTS` | `kickback-processPayouts` | nested under manage | Create, cancel, complete, fail, process (via gateway) individual + batch payouts |
| `PERMISSION_VERIFY_PAYOUTS` | `kickback-verifyPayouts` | nested under manage | Approve / reject entries in the payout verification queue (four-eyes review) |
| `PERMISSION_MANAGE_PROGRAMS` | `kickback-managePrograms` | root | Program CRUD |
| `PERMISSION_VIEW_REPORTS` | `kickback-viewReports` | root | View + export reports |
| `PERMISSION_MANAGE_SETTINGS` | `kickback-manageSettings` | root | Edit plugin settings |

Nesting in Craft means granting a nested permission also implies its parent.
You cannot hold `approveAffiliates` without `manageAffiliates`.

## Manage-vs-approve split

Several resources expose a "manage" tier (read / list / export) and a separate
"approve" tier (authoritative state change). Mutation actions check the
**approve** tier explicitly, on top of whatever `beforeAction` already
enforced. Two examples:

- `CommissionsController::actionApprove|Reject|Reverse` checks
  `APPROVE_COMMISSIONS`.
- `AffiliatesController::actionSave` only accepts `affiliateStatus` and
  `commissionRateOverride` from the form when the caller holds
  `APPROVE_AFFILIATES`; users with only manage can still edit everything else.
- `ReferralsController::actionApprove|Reject` checks `APPROVE_REFERRALS`,
  while list/export remains under `MANAGE_REFERRALS`.

When adding a new mutation, add an explicit `requirePermission()` call - do
not rely on the nav-level gating alone.

## Payout verification

`VERIFY_PAYOUTS` is independent of `PROCESS_PAYOUTS`: a verifier can approve a
pending payout in the approvals queue but cannot push it through the gateway,
and a processor can push payouts through the gateway but - when
`requirePayoutVerification` is on - must wait for a verifier's signoff first.
This is the four-eyes pattern; see [flows.md](flows.md#payout-verification).

## CP nav gating

`KickBack::getCpNavItem()` shows a subnav entry only if the current user has
the corresponding permission (dashboard excepted - always visible). Approval
permissions also backfill the relevant read nav (`Referrals`/`Fraud` for
`APPROVE_REFERRALS`, `Commissions` for `APPROVE_COMMISSIONS`) so reviewers can
access their queues without broad manage access. This is presentation only;
controllers still enforce their own checks.

## Settings-gated portal routes

Not every portal route is permission-gated - some are gated on plugin
settings instead. `/{portalPath}/team` is gated on `Settings::$enableMultiTier`,
not on a permission. There is no new permission to grant: when the setting is
off, `PortalController::actionTeam()` throws `NotFoundHttpException` for
everyone (including admins), and the "Team" nav link is hidden.
