<?php

declare(strict_types=1);

namespace anvildev\craftkickback\console\controllers;

use anvildev\craftkickback\elements\AffiliateElement;
use anvildev\craftkickback\elements\CommissionElement;
use anvildev\craftkickback\elements\PayoutElement;
use anvildev\craftkickback\records\CommissionRecord;
use anvildev\craftkickback\records\PayoutRecord;
use Craft;
use craft\console\Controller;
use yii\console\ExitCode;

/**
 * Recompute affiliate balances from commission + payout history. Dry-run
 * by default - pass --dryRun=0 to write.
 */
class BalancesController extends Controller
{
    public bool $dryRun = true;
    public ?int $affiliateId = null;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['dryRun', 'affiliateId']);
    }

    public function actionRecompute(): int
    {
        $query = AffiliateElement::find();
        if ($this->affiliateId !== null) {
            $query->id($this->affiliateId);
        }

        $mismatches = 0;
        foreach ($query->each() as $affiliate) {
            /** @var AffiliateElement $affiliate */
            $approvedUnpaid = (float)(CommissionRecord::find()
                ->where([
                    'affiliateId' => $affiliate->id,
                    'status' => CommissionElement::STATUS_APPROVED,
                    'payoutId' => null,
                ])
                ->sum('amount') ?? 0);

            $lifetimeFromPayouts = (float)(PayoutRecord::find()
                ->where([
                    'affiliateId' => $affiliate->id,
                    'status' => PayoutElement::STATUS_COMPLETED,
                ])
                ->sum('amount') ?? 0);

            $pendingDiff = round($approvedUnpaid - (float)$affiliate->pendingBalance, 2);
            $lifetimeDiff = round($lifetimeFromPayouts - (float)$affiliate->lifetimeEarnings, 2);

            if (abs($pendingDiff) < 0.01 && abs($lifetimeDiff) < 0.01) {
                continue;
            }

            $mismatches++;
            $this->stdout("Affiliate #{$affiliate->id} ({$affiliate->title})\n");
            $this->stdout("  pendingBalance: stored={$affiliate->pendingBalance} expected={$approvedUnpaid} diff={$pendingDiff}\n");
            $this->stdout("  lifetime:       stored={$affiliate->lifetimeEarnings} expected={$lifetimeFromPayouts} diff={$lifetimeDiff}\n");

            if (!$this->dryRun) {
                $affiliate->pendingBalance = $approvedUnpaid;
                $affiliate->lifetimeEarnings = $lifetimeFromPayouts;
                Craft::$app->getElements()->saveElement($affiliate, false);
                $this->stdout("  -> corrected\n");
            }
        }

        $note = $this->dryRun ? ' (dry run - pass --dryRun=0 to fix)' : ' (corrected)';
        $this->stdout("Found {$mismatches} mismatch(es){$note}\n");
        return ExitCode::OK;
    }
}
