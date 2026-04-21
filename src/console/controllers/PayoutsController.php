<?php

declare(strict_types=1);

namespace anvildev\craftkickback\console\controllers;

use anvildev\craftkickback\jobs\BatchPayoutJob;
use anvildev\craftkickback\KickBack;
use Craft;
use craft\console\Controller;
use yii\console\ExitCode;

/**
 * Scheduled payout automation commands.
 */
class PayoutsController extends Controller
{
    public bool $dryRun = false;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), [
            'dryRun',
        ]);
    }

    /**
     * Enqueue a batch payout job if the configured cadence has elapsed.
     * Intended to run hourly from a system crontab.
     */
    public function actionAutoRun(): int
    {
        $plugin = KickBack::getInstance();
        $settings = $plugin->getSettings();

        if (!$settings->batchAutoProcessEnabled) {
            if ($this->dryRun) {
                $this->stdout("Scheduled auto-processing is disabled. No action.\n");
            }
            return ExitCode::OK;
        }

        $lastRun = $this->parseLastRun($settings->batchAutoProcessLastRun);
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        if (!$plugin->payouts->shouldAutoRun($settings->batchAutoProcessCadence, $lastRun, $now)) {
            if ($this->dryRun) {
                $this->stdout(sprintf(
                    "Cadence '%s' not due (today: %s, last run: %s). No action.\n",
                    $settings->batchAutoProcessCadence,
                    $now->format('Y-m-d D'),
                    $lastRun?->format('Y-m-d') ?? 'never',
                ));
            }
            return ExitCode::OK;
        }

        if ($this->dryRun) {
            $this->stdout(sprintf(
                "Would enqueue BatchPayoutJob (cadence: %s, today: %s, last run: %s)\n",
                $settings->batchAutoProcessCadence,
                $now->format('Y-m-d D'),
                $lastRun?->format('Y-m-d') ?? 'never',
            ));
            return ExitCode::OK;
        }

        // Stamp before enqueue so overlapping cron ticks don't double-fire.
        if (!$plugin->payouts->recordAutoRun()) {
            $this->stderr("Failed to record auto-run timestamp. Aborting to avoid double runs.\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        Craft::$app->getQueue()->push(new BatchPayoutJob([
            'notes' => 'Scheduled auto-run (' . $settings->batchAutoProcessCadence . ')',
            'autoProcess' => true,
        ]));

        $this->stdout("Enqueued BatchPayoutJob for scheduled auto-run ({$settings->batchAutoProcessCadence}).\n");
        Craft::info("Scheduled payout auto-run enqueued ({$settings->batchAutoProcessCadence})", __METHOD__);

        return ExitCode::OK;
    }

    private function parseLastRun(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
        } catch (\Throwable) {
            return null;
        }
    }
}
