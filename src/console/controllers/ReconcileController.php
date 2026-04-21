<?php

declare(strict_types=1);

namespace anvildev\craftkickback\console\controllers;

use anvildev\craftkickback\jobs\ReconcilePayoutsJob;
use Craft;
use craft\console\Controller;
use yii\console\ExitCode;

/**
 * Manually trigger payout reconciliation.
 */
class ReconcileController extends Controller
{
    public int $days = 7;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['days']);
    }

    public function actionRun(): int
    {
        if ($this->days < 1) {
            $this->stderr("--days must be >= 1\n");
            return ExitCode::USAGE;
        }

        $job = new ReconcilePayoutsJob(['days' => $this->days]);
        Craft::$app->getQueue()->push($job);
        $this->stdout("Queued ReconcilePayoutsJob (days={$this->days})\n");
        return ExitCode::OK;
    }
}
