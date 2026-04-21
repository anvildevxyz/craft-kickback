# Quickstart

From fresh install to seeing your first commission, in ten minutes. Assumes
Craft CMS 5.8+ and Craft Commerce are already set up.

## 1. Install

```bash
composer require anvildev/craft-kickback
php craft plugin/install kickback
```

Install creates a "Default" program at 10% commission, so affiliates have
somewhere to land immediately. Verify with:

```bash
php craft kickback/health/check
```

You should see `Healthy.` on a brand-new install. If the health check flags a
missing gateway or program, fix that before continuing.

## 2. Enable the affiliate portal (optional)

Install auto-enables the portal on the primary site at `/affiliate`. Skip
this step if that default works for you.

If you want a different path, or want the portal enabled on additional sites:

1. **Settings → Kickback → Portal**
2. For each site, flip the **"Enable on this site"** lightswitch and set a
   URL path (e.g. `affiliate`, `partner`, `partners/program`)
3. Save

Equivalent via `config/kickback.php`:

```php
return [
    '*' => [
        'affiliatePortalEnabledSites' => ['default' => true, 'de' => true],
        'affiliatePortalPaths' => ['default' => 'affiliate', 'de' => 'partner'],
    ],
];
```

## 3. Create a test affiliate

Two paths:

- **Via the portal** (recommended to test the real flow). Visit
  `/<your-path>/register` anonymously and fill in the form. If
  `autoApproveAffiliates` is off (the default), the new record lands in
  **pending** status; go to **Kickback → Affiliates** in the CP and click
  **Approve**.
- **Via the CP directly.** **Kickback → Affiliates → + New affiliate**. Pick
  an existing Craft user, assign a program, save with status `active`.

Either way, grab the affiliate's **referral code** from the edit screen -
that's the string you'll use in the next step.

## 4. Trigger a click + order

In an incognito window (so cookies are clean):

1. Visit `https://<your-site>/r/<referralCode>` - you should be redirected
   back to the homepage and a `_kb_ref` cookie should be set
2. Add a product to cart and check out as the customer
3. Complete the order (mark it `Paid` if you're using manual payments)

Within seconds, the `Order::EVENT_AFTER_COMPLETE_ORDER` listener fires,
`ReferralService::processOrder` runs, and a referral + commission are
created. See [flows.md](flows.md#1-click--referral--commission--payout) for
the full sequence.

## 5. See it in the CP

- **Kickback → Dashboard** - the stats should show one new referral and one
  new commission
- **Kickback → Referrals** - your new referral, status depends on your
  `autoApproveReferrals` + `holdPeriodDays` settings
- **Kickback → Commissions** - the commission row

If the commission is `pending`, click it and use the **Approve** action.
That moves it into the affiliate's `pendingBalance`.

## 6. Pay out

The simplest test uses the **Manual** gateway so you don't need PayPal or
Stripe configured:

1. On the affiliate's edit page, set **Payout Method** to `Manual`
2. **Kickback → Payouts → + New payout** for that affiliate
3. The payout moves through `pending → processing → completed` and the
   affiliate's `pendingBalance` is deducted into `lifetimeEarnings`

For PayPal / Stripe Connect payouts, see [gateways.md](gateways.md) -
credentials, webhook setup, and reconciliation all live there.

## 7. Shortcut: demo data for local dev

If you're experimenting and just want something to click around:

```bash
php craft kickback/seed --fresh
```

Requires `devMode = true`. Wipes existing Kickback data and seeds
programs, affiliate groups, affiliates (one per status), commission rules
across all rule types, clicks, referrals, commissions, payouts, and
coupons. See [routes.md#kickbackseed](routes.md#kickbackseed).

## Where to go next

- **Hooking into events** - [events.md](events.md) for the constant list +
  complete `Event::on()` examples
- **Commission rate resolution** - [architecture.md#commission-rate-resolution](architecture.md#commission-rate-resolution)
- **Multi-tier / MLM** - turn on `Settings::$enableMultiTier`, then see
  [flows.md#7-two-tier-recruiting](flows.md#7-two-tier-recruiting)
- **Headless / GraphQL** - [graphql.md](graphql.md) (queries only; no
  mutations in 1.x)
- **Production hardening** - [the Production Recommendations section in the
  root README](../README.md#production-recommendations)
