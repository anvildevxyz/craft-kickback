# Troubleshooting

Short operator-facing diagnostics for the most common issues. For deeper
behavior, cross-reference the relevant doc linked in each section.

## SubID column missing on portal referrals page

The migration hasn't run yet, or Craft is serving a stale template. Run:

```bash
php craft migrate/up --plugin=kickback
php craft clear-caches/all
```

## Bulk coupon CP form returns 500

Check `storage/logs/web-*.log`. The most common cause is the hidden
`affiliateId` field in the form pointing to an affiliate that no longer
exists. See [services.md#couponservice](services.md#couponservice-plugin-coupons)
for the underlying validation.

## Approval email doesn't mention the recruiter

Recruiter mention only fires when `$affiliate->parentAffiliateId` is non-null
**and** the parent affiliate row still exists. If the parent was deleted or
suspended between signup and approval, the email falls back to the generic
welcome copy.

## `/affiliate/team` returns 404 with multi-tier enabled

Confirm `Settings::$enableMultiTier` is `true` and the affiliate is logged in
and approved. The route is hidden for anonymous or pending affiliates even
when the setting is on. See [permissions.md#settings-gated-portal-routes](permissions.md#settings-gated-portal-routes).

## MLM tier 2 commission not created on a child's referral

All three conditions must hold:

1. `Settings::$enableMultiTier` is `true`
2. A commission rule exists on the program with `type = 'mlm_tier'` and
   `tierLevel = 2`
3. The parent affiliate's `affiliateStatus` is `active`

See [flows.md#7-two-tier-recruiting](flows.md#7-two-tier-recruiting).

## PayPal payout stuck in `processing`

The webhook isn't reaching your site. Verify:

1. The webhook URL in the PayPal dashboard matches
   `https://<your-site>/kickback/webhooks/paypal`
2. Your site is publicly reachable (not behind a firewall or VPN)
3. `Settings::$paypalWebhookId` is set

As a safety net, run `craft kickback/reconcile/run --days=7` to query PayPal
directly for recent batch statuses. See [gateways.md#reconciliation](gateways.md#reconciliation).

## Stripe transfer throws an API version mismatch

Copy the full error from `storage/logs/web-*.log` and open an issue. Do not
downgrade the `stripe/stripe-php` package to work around it; the gateway is
pinned to the version the plugin expects.
