<?php

declare(strict_types=1);

namespace anvildev\craftkickback\console\controllers;

use anvildev\craftkickback\elements\PayoutElement;
use anvildev\craftkickback\helpers\DateHelper;
use anvildev\craftkickback\KickBack;
use anvildev\craftkickback\records\PayoutRecord;
use craft\console\Controller;
use yii\console\ExitCode;

/**
 * Quick plugin self-test. Exits non-zero if any problem is found.
 */
class HealthController extends Controller
{
    public function actionCheck(): int
    {
        $plugin = KickBack::getInstance();
        $problems = [];

        if (!class_exists(\craft\commerce\Plugin::class)) {
            $problems[] = 'Craft Commerce is not installed - most features will no-op.';
        }

        if ($plugin->programs->getDefaultProgram() === null) {
            $problems[] = 'No default program exists. Run craft kickback/seed/default-program or create one in the CP.';
        }

        if (empty($plugin->payoutGateways->getConfiguredGateways())) {
            $problems[] = 'No payout gateway is configured. Payouts cannot be processed.';
        }

        $settings = $plugin->getSettings();
        if ($settings->requirePayoutVerification && $settings->defaultPayoutVerifierId === null) {
            $problems[] = 'requirePayoutVerification is on but defaultPayoutVerifierId is not set.';
        }

        $staleCutoff = DateHelper::pastCutoffString('-24 hours');
        $stale = (int)PayoutRecord::find()
            ->where(['status' => PayoutElement::STATUS_PROCESSING])
            ->andWhere(['<', 'dateUpdated', $staleCutoff])
            ->count();
        if ($stale > 0) {
            $problems[] = "{$stale} payout(s) stuck in 'processing' for >24h - run kickback/reconcile/run.";
        }

        if (empty($problems)) {
            $this->stdout("Healthy.\n");
            return ExitCode::OK;
        }

        foreach ($problems as $p) {
            $this->stderr("  ! {$p}\n");
        }
        return ExitCode::UNSPECIFIED_ERROR;
    }
}
