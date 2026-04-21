<?php

declare(strict_types=1);

namespace anvildev\craftkickback\gateways;

use anvildev\craftkickback\elements\PayoutElement;

/**
 * Gateways that can be polled for the live status of a previously-sent
 * payout implement this interface. The reconciliation job uses it to
 * detect webhook delivery failures and silent dropouts.
 *
 * Return values are one of three stringly-typed states:
 *  - 'completed' - the provider still reports the transfer as cleared
 *  - 'reversed'  - fully reversed on the provider side; the job will
 *                  call PayoutService::markReversed() to restore the
 *                  affiliate balance
 *  - 'unknown'   - the provider didn't give us a clear answer (network
 *                  error, auth failure, unknown transaction id). The job
 *                  logs and skips; next run will try again.
 */
interface ReconciliationCapableInterface
{
    public function fetchPayoutStatus(PayoutElement $payout): string;
}
