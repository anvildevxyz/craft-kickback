<?php

declare(strict_types=1);

namespace anvildev\craftkickback\controllers;

use anvildev\craftkickback\helpers\CsvExportHelper;
use anvildev\craftkickback\KickBack;
use anvildev\craftkickback\records\CommissionRecord;
use Craft;
use craft\web\Controller;
use yii\web\Response;

/**
 * Date-filtered stats, top affiliates, chart data, and CSV export.
 */
class ReportsController extends Controller
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission(KickBack::PERMISSION_VIEW_REPORTS);

        return true;
    }

    public function actionIndex(): Response
    {
        $plugin = KickBack::getInstance();
        $reporting = $plugin->reporting;

        $request = Craft::$app->getRequest();
        $startDate = $request->getQueryParam('startDate');
        $endDate = $request->getQueryParam('endDate');
        $preset = $request->getQueryParam('preset');

        if ($preset !== null) {
            [$startDate, $endDate] = $reporting->resolveDatePreset($preset);
        }

        return $this->renderTemplate('kickback/reports/index', [
            'stats' => $reporting->getStats($startDate, $endDate),
            'topAffiliates' => $reporting->getTopAffiliates(),
            'settings' => $plugin->getSettings(),
            'startDate' => $startDate,
            'endDate' => $endDate,
            'preset' => $preset,
            'dailyCommissions' => $reporting->getDailyCommissions($startDate, $endDate),
            'dailyReferrals' => $reporting->getDailyReferrals($startDate, $endDate),
            'currency' => KickBack::getCommerceCurrency(),
        ]);
    }

    public function actionExport(): void
    {
        $plugin = KickBack::getInstance();
        $request = Craft::$app->getRequest();
        $startDate = $request->getQueryParam('startDate');
        $endDate = $request->getQueryParam('endDate');

        $suffix = ($startDate && $endDate) ? $startDate . '_to_' . $endDate : 'all';

        CsvExportHelper::streamAsDownload(
            ['ID', 'Affiliate', 'Referral ID', 'Amount', 'Currency', 'Rate', 'Rate Type', 'Rule Applied', 'Tier', 'Status', 'Date Created', 'Date Approved', 'Date Reversed'],
            function(int $offset, int $limit) use ($plugin, $startDate, $endDate): array {
                $query = CommissionRecord::find()
                    ->orderBy(['dateCreated' => SORT_DESC])
                    ->offset($offset)
                    ->limit($limit);

                if ($startDate !== null && $endDate !== null) {
                    $query->andWhere(['between', 'dateCreated', $startDate . ' 00:00:00', $endDate . ' 23:59:59']);
                } elseif ($startDate !== null) {
                    $query->andWhere(['>=', 'dateCreated', $startDate . ' 00:00:00']);
                } elseif ($endDate !== null) {
                    $query->andWhere(['<=', 'dateCreated', $endDate . ' 23:59:59']);
                }

                /** @var CommissionRecord[] $commissions */
                $commissions = $query->all();
                if (empty($commissions)) {
                    return [];
                }

                $affiliateIds = array_map(fn($c) => $c->affiliateId, $commissions);
                $affiliates = $plugin->affiliates->getAffiliatesByIds($affiliateIds);

                return array_map(fn($commission) => [
                    $commission->id,
                    ($affiliates[$commission->affiliateId] ?? null)?->title ?? '',
                    $commission->referralId ?? '',
                    $commission->amount,
                    $commission->currency,
                    $commission->rate,
                    $commission->rateType,
                    $commission->ruleApplied ?? '',
                    $commission->tier,
                    $commission->status,
                    $commission->dateCreated ?? '',
                    $commission->dateApproved ?? '',
                    $commission->dateReversed ?? '',
                ], $commissions);
            },
            'report-commissions-' . $suffix . '-' . date('Y-m-d') . '.csv',
        );
    }
}
