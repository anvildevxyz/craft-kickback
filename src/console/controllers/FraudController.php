<?php

declare(strict_types=1);

namespace anvildev\craftkickback\console\controllers;

use anvildev\craftkickback\helpers\DateHelper;
use anvildev\craftkickback\KickBack;
use anvildev\craftkickback\records\ReferralRecord;
use craft\console\Controller;
use yii\console\ExitCode;

/**
 * Re-run fraud detection against existing referrals, e.g. after tuning
 * thresholds in Settings.
 */
class FraudController extends Controller
{
    public ?int $days = null;
    public bool $dryRun = false;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['days', 'dryRun']);
    }

    public function actionReevaluate(): int
    {
        $plugin = KickBack::getInstance();

        $query = ReferralRecord::find();
        if ($this->days !== null) {
            if ($this->days < 1) {
                $this->stderr("--days must be >= 1\n");
                return ExitCode::USAGE;
            }
            $cutoff = DateHelper::pastCutoffString("-{$this->days} days");
            $query->andWhere(['>=', 'dateCreated', $cutoff]);
        }

        $total = (int)(clone $query)->count();
        $this->stdout("Re-evaluating {$total} referral(s)" . ($this->dryRun ? ' (dry run)' : '') . "\n");

        $flagged = 0;
        foreach ($query->each() as $record) {
            /** @var ReferralRecord $record */
            $reasons = $plugin->fraud->evaluateReferral($record);
            if (empty($reasons)) {
                continue;
            }

            $flagged++;
            $this->stdout("  Referral #{$record->id}: " . implode('; ', $reasons) . "\n");
            if (!$this->dryRun) {
                $plugin->fraud->flagReferral($record, $reasons);
            }
        }

        $this->stdout("Done. {$flagged} referral(s) " . ($this->dryRun ? 'would be' : 'were') . " flagged.\n");
        return ExitCode::OK;
    }
}
