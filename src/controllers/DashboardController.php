<?php

declare(strict_types=1);

namespace anvildev\craftkickback\controllers;

use anvildev\craftkickback\elements\AffiliateElement;
use anvildev\craftkickback\KickBack;
use anvildev\craftkickback\models\Commission;
use anvildev\craftkickback\models\Referral;
use anvildev\craftkickback\records\CommissionRecord;
use anvildev\craftkickback\records\ReferralRecord;
use craft\db\Query;
use craft\web\Controller;
use yii\web\Response;

/**
 * Admin dashboard: overview stats, recent activity, and commission charts.
 */
class DashboardController extends Controller
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission(KickBack::PERMISSION_MANAGE_AFFILIATES);

        return true;
    }

    public function actionIndex(): Response
    {
        $plugin = KickBack::getInstance();
        $settings = $plugin->getSettings();

        $stats = $this->getOverviewStats();

        $recentAffiliates = AffiliateElement::find()
            ->orderBy(['dateCreated' => SORT_DESC])
            ->limit(5)
            ->all();

        $pendingCount = AffiliateElement::find()
            ->affiliateStatus('pending')
            ->count();

        /** @var ReferralRecord[] $recentReferrals */
        $recentReferrals = ReferralRecord::find()
            ->orderBy(['dateCreated' => SORT_DESC])
            ->limit(5)
            ->all();

        /** @var CommissionRecord[] $recentCommissions */
        $recentCommissions = CommissionRecord::find()
            ->orderBy(['dateCreated' => SORT_DESC])
            ->limit(5)
            ->all();

        $affiliateIds = array_merge(
            array_map(fn($r) => $r->affiliateId, $recentReferrals),
            array_map(fn($c) => $c->affiliateId, $recentCommissions),
        );
        $affiliates = $plugin->affiliates->getAffiliatesByIds($affiliateIds);

        $thirtyDaysAgo = (new \DateTime())->modify('-30 days')->format('Y-m-d');
        $today = (new \DateTime())->format('Y-m-d');

        $dailyCommissions = (new Query())
            ->select(['DATE(dateCreated) as date', 'SUM(amount) as total'])
            ->from('{{%kickback_commissions}}')
            ->where(['between', 'dateCreated', $thirtyDaysAgo . ' 00:00:00', $today . ' 23:59:59'])
            ->groupBy(['DATE(dateCreated)'])
            ->orderBy(['date' => SORT_ASC])
            ->all();

        return $this->renderTemplate('kickback/dashboard/index', [
            'stats' => $stats,
            'recentAffiliates' => $recentAffiliates,
            'recentReferrals' => $recentReferrals,
            'recentCommissions' => $recentCommissions,
            'affiliates' => $affiliates,
            'pendingCount' => $pendingCount,
            'settings' => $settings,
            'dailyCommissions' => $dailyCommissions,
            'currency' => KickBack::getCommerceCurrency(),
        ]);
    }

    /**
     * @return array<string, int|float|string>
     */
    private function getOverviewStats(): array
    {
        $totalAffiliates = (int)AffiliateElement::find()->count();
        $activeAffiliates = (int)AffiliateElement::find()->affiliateStatus('active')->count();

        $commQ = fn() => (new Query())->from('{{%kickback_commissions}}');
        $totalReferrals = (int)(new Query())->from('{{%kickback_referrals}}')->count();
        $totalCommissions = (float)($commQ()->sum('amount') ?? 0);
        $pendingCommissions = (float)($commQ()->where(['status' => Commission::STATUS_PENDING])->sum('amount') ?? 0);
        $approvedCommissions = (float)($commQ()->where(['status' => Commission::STATUS_APPROVED])->sum('amount') ?? 0);
        $totalClicks = (int)(new Query())->from('{{%kickback_clicks}}')->count();
        $flaggedReferrals = (int)(new Query())->from('{{%kickback_referrals}}')->where(['status' => Referral::STATUS_FLAGGED])->count();

        return [
            'totalAffiliates' => $totalAffiliates,
            'activeAffiliates' => $activeAffiliates,
            'totalReferrals' => $totalReferrals,
            'totalCommissions' => $totalCommissions,
            'pendingCommissions' => $pendingCommissions,
            'approvedCommissions' => $approvedCommissions,
            'totalClicks' => $totalClicks,
            'flaggedReferrals' => $flaggedReferrals,
            'conversionRate' => $totalClicks > 0 ? round($totalReferrals / $totalClicks * 100, 1) : 0,
        ];
    }
}
