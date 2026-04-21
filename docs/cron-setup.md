# Scheduled Payout Auto-Processing

KickBack can run batch payouts automatically on a schedule (weekly, bi-weekly, monthly, or quarterly). When enabled, it creates payouts for every eligible affiliate and sends PayPal/Stripe payouts via the gateway API - no admin interaction required.

## How it works

1. Enable "Scheduled auto-processing" in `/admin/kickback/settings` (Payouts tab)
2. Pick a cadence (default: Monthly on the 1st)
3. Add one line to your server's crontab (see below)
4. Done - the scheduler handles the rest

Cron ticks hourly. The `kickback/payouts/auto-run` command is a silent no-op on most ticks; it only enqueues a `BatchPayoutJob` when the configured cadence interval has elapsed since the last successful run.

## Crontab setup

Add this line to your server's crontab (`crontab -e` as the user that runs PHP):

```cron
0 * * * *   cd /path/to/your/site && ./craft kickback/payouts/auto-run
```

Replace `/path/to/your/site` with the absolute path to your Craft project. For DDEV:

```cron
0 * * * *   cd /var/www/html && ./craft kickback/payouts/auto-run
```

The command writes to Craft's log (`storage/logs/web.log`) and queue. No separate log file is required, but you can redirect stdout/stderr if you want a dedicated trail:

```cron
0 * * * *   cd /var/www/html && ./craft kickback/payouts/auto-run >> /var/log/kickback-cron.log 2>&1
```

## Cadence semantics

| Cadence | Fires on |
|---|---|
| Weekly | Every Monday |
| Bi-weekly | Every other Monday (tracked via last-run timestamp) |
| **Monthly** (default) | 1st of every month |
| Quarterly | 1st of January, April, July, October |

All times are UTC. If your affiliate cycle needs a different day, the cadence presets are hard-coded - you'd need to extend `PayoutService::shouldAutoRun()` to support custom days.

## Testing

### Dry-run mode

The command supports `--dry-run` to simulate without enqueueing anything or updating the last-run timestamp:

```bash
./craft kickback/payouts/auto-run --dry-run
```

Output examples:

- **Disabled:** `Scheduled auto-processing is disabled. No action.`
- **Cadence not met:** `Cadence 'monthly' not due (today: 2026-04-10 Fri, last run: 2026-04-01). No action.`
- **Would fire:** `Would enqueue BatchPayoutJob (cadence: monthly, today: 2026-05-01 Fri, last run: 2026-04-01)`

### Manual trigger (bypass cadence check)

To run a batch on demand without waiting for the next cadence day, use the existing admin button at `/admin/kickback/payouts/batch` with the "Auto-process via payment gateways" checkbox enabled. This bypasses the scheduler entirely.

## Double-run prevention

The command updates `batchAutoProcessLastRun` **before** enqueueing the job. If two cron ticks fire in the same second (unusual but possible), the second one sees the fresh timestamp and exits silently.

Tradeoff: if the queue worker crashes after the timestamp is updated but before `BatchPayoutJob` actually executes, the current cycle is skipped. You can recover by clicking "Run batch payouts" manually from the admin UI - it uses the same underlying batch job and will pick up all eligible affiliates.

## What the scheduled job does

When the cadence is due, the command enqueues a `BatchPayoutJob` identical to what the admin "Run batch payouts" button would enqueue, except:

- `autoProcess` is forced to `true` (gateway auto-send is always enabled for scheduled runs - that's the whole point)
- `notes` is set to `"Scheduled auto-run (monthly)"` or similar, so admins can see which payouts came from the scheduler vs. manual runs

Affiliates below the `minimumPayoutAmount` threshold are skipped automatically - the job reuses `PayoutService::getEligibleAffiliates()`.

## Monitoring

- **Last run timestamp:** visible at `/admin/kickback/settings` (Payouts tab → "Last scheduled run")
- **Queue status:** `/admin/utilities/queue-manager` shows pending and failed `BatchPayoutJob` runs
- **Craft log:** `storage/logs/web.log` contains lines like `Scheduled payout auto-run enqueued (monthly)`
- **Payout notes:** scheduled payouts have notes containing "Scheduled auto-run" so they're filterable in the payouts index

## Disabling

Flip the "Enable scheduled auto-processing" lightswitch off in settings. You can leave the crontab line in place - the command will exit silently whenever the setting is off.
