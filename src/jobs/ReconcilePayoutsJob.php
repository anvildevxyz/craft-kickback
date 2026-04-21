<?php

declare(strict_types=1);

namespace anvildev\craftkickback\jobs;

use anvildev\craftkickback\elements\PayoutElement;
use anvildev\craftkickback\gateways\ReconciliationCapableInterface;
use anvildev\craftkickback\helpers\DateHelper;
use anvildev\craftkickback\KickBack;
use Craft;
use craft\queue\BaseJob;

/**
 * Walk recently-completed payouts and ask the gateway whether they actually
 * cleared. Catches dropped webhooks. Idempotent.
 */
class ReconcilePayoutsJob extends BaseJob
{
    public int $days = 7;

    public function execute($queue): void
    {
        $query = PayoutElement::find()
            ->payoutStatus(PayoutElement::STATUS_COMPLETED)
            ->andWhere(['>=', 'elements.dateUpdated', DateHelper::pastCutoffString("-{$this->days} days")]);

        $total = (int)$query->count();
        $processed = $reconciled = $unknown = 0;
        $plugin = KickBack::getInstance();

        foreach ($query->each(100) as $payout) {
            $this->setProgress($queue, $total > 0 ? ++$processed / $total : 1);

            $gateway = $plugin->payoutGateways->getGateway($payout->method);
            if (!$gateway instanceof ReconciliationCapableInterface) {
                continue;
            }

            $status = $gateway->fetchPayoutStatus($payout);
            if ($status === 'reversed') {
                $plugin->payouts->markReversed($payout, 'reconciliation');
                $reconciled++;
                Craft::warning("Reconciliation reversed payout #{$payout->id}", __METHOD__);
            } elseif ($status === 'unknown') {
                $unknown++;
                Craft::info("Reconciliation skipped payout #{$payout->id} - gateway returned unknown status", __METHOD__);
            }
        }

        Craft::info("Reconciliation: checked {$total} payouts, reversed {$reconciled}, unknown {$unknown}", __METHOD__);
    }

    protected function defaultDescription(): ?string
    {
        return 'Reconcile recent payouts against gateway status';
    }
}
