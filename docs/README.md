# Kickback developer docs

Developer reference for the Kickback affiliate & referral marketing plugin.
For a feature-level overview aimed at end users / site admins, see the
[top-level README](../README.md); the files here focus on the code.

## Contents

Start here:

- **[quickstart.md](quickstart.md)** - from `composer require` to a first
  commission in ten minutes
- **[architecture.md](architecture.md)** - directory layout, element types,
  service layer at a glance
- **[bootstrap.md](bootstrap.md)** - what `KickBack::init()` registers and in
  what order
- **[routes.md](routes.md)** - full CP + site route reference
- **[permissions.md](permissions.md)** - all 12 permissions, manage/approve
  split, nav gating
- **[configuration.md](configuration.md)** - every `Settings` property grouped
  by concern, with defaults and validation notes
- **[flows.md](flows.md)** - end-to-end flows: click→payout, refund reversal,
  approval workflow, batch auto-processing, portal onboarding, silent
  `?ref=` capture

Reference:

- [services.md](services.md) - service API reference (method signatures,
  usage examples)
- [events.md](events.md) - event constants and handler examples
- [models.md](models.md) - data models, status lifecycles, relationships
- [gateways.md](gateways.md) - `PayoutGatewayInterface` and implementing
  custom gateways
- [cron-setup.md](cron-setup.md) - scheduling the batch payout auto-run
- [troubleshooting.md](troubleshooting.md) - short operator-facing diagnostics
  for common issues (stuck payouts, missing MLM commissions, etc.)

## Quick reference

### Accessing services

```php
use anvildev\craftkickback\KickBack;

$plugin = KickBack::getInstance();

$plugin->affiliates;        // AffiliateService
$plugin->affiliateGroups;   // AffiliateGroupService
$plugin->programs;          // ProgramService
$plugin->tracking;          // TrackingService
$plugin->referrals;         // ReferralService
$plugin->commissions;       // CommissionService
$plugin->commissionRules;   // CommissionRuleService
$plugin->coupons;           // CouponService
$plugin->fraud;             // FraudService
$plugin->payouts;           // PayoutService
$plugin->payoutGateways;    // PayoutGatewayService
$plugin->notifications;     // NotificationService
$plugin->reporting;         // ReportingService
$plugin->approvals;         // ApprovalService
```

### Twig variable

```twig
{% set affiliate = craft.kickback.currentAffiliate %}
{% set activeCode = craft.kickback.activeReferralCode() %}
{% set usedOrderCode = craft.kickback.referralCodeForOrder(order.id) %}
```

### Namespace

All classes live under `anvildev\craftkickback`.
