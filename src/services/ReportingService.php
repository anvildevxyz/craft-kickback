<?php

declare(strict_types=1);

namespace anvildev\craftkickback\services;

use anvildev\craftkickback\elements\AffiliateElement;
use anvildev\craftkickback\elements\PayoutElement;
use anvildev\craftkickback\models\Commission;
use anvildev\craftkickback\models\Referral;
use craft\base\Component;
use craft\db\Query;

/**
 * Reporting queries for stats, charts, and top affiliates.
 */
class ReportingService extends Component
{
    /**
     * @return array{0: ?string, 1: ?string}
     */
    public function resolveDatePreset(string $preset): array
    {
        $now = new \DateTime();
        if ($preset === 'lastMonth') {
            $m = (clone $now)->modify('first day of last month');
            return [$m->format('Y-m-01'), $m->format('Y-m-t')];
        }
        return match ($preset) {
            'thisMonth' => [$now->format('Y-m-01'), $now->format('Y-m-d')],
            'last30' => [(clone $now)->modify('-30 days')->format('Y-m-d'), $now->format('Y-m-d')],
            'thisYear' => [$now->format('Y-01-01'), $now->format('Y-m-d')],
            default => [null, null],
        };
    }

    /**
     * @return array{totalAffiliates: int, activeAffiliates: int, totalReferrals: int, approvedReferrals: int, totalCommissions: float, approvedCommissions: float, pendingCommissions: float, totalClicks: int, totalPayouts: float}
     */
    public function getStats(?string $startDate = null, ?string $endDate = null): array
    {
        $referrals = fn() => $this->applyDateFilter((new Query())->from('{{%kickback_referrals}}'), $startDate, $endDate);
        $commissions = fn() => $this->applyDateFilter((new Query())->from('{{%kickback_commissions}}'), $startDate, $endDate);

        return [
            'totalAffiliates' => (int)AffiliateElement::find()->count(),
            'activeAffiliates' => (int)AffiliateElement::find()->affiliateStatus(AffiliateElement::STATUS_ACTIVE)->count(),
            'totalReferrals' => (int)$referrals()->count(),
            'approvedReferrals' => (int)$referrals()->andWhere(['status' => Referral::STATUS_APPROVED])->count(),
            'totalCommissions' => (float)($commissions()->sum('amount') ?? 0),
            'approvedCommissions' => (float)($commissions()->andWhere(['status' => Commission::STATUS_APPROVED])->sum('amount') ?? 0),
            'pendingCommissions' => (float)($commissions()->andWhere(['status' => Commission::STATUS_PENDING])->sum('amount') ?? 0),
            'totalClicks' => (int)$this->applyDateFilter((new Query())->from('{{%kickback_clicks}}'), $startDate, $endDate)->count(),
            'totalPayouts' => (float)($this->applyDateFilter((new Query())->from('{{%kickback_payouts}}'), $startDate, $endDate)
                ->andWhere(['status' => PayoutElement::STATUS_COMPLETED])->sum('amount') ?? 0),
        ];
    }

    /**
     * @return AffiliateElement[]
     */
    public function getTopAffiliates(int $limit = 10): array
    {
        return AffiliateElement::find()
            ->orderBy(['kickback_affiliates.lifetimeEarnings' => SORT_DESC])
            ->limit($limit)
            ->all();
    }

    /**
     * @return array<array{date: string, total: float}>
     */
    public function getDailyCommissions(?string $startDate = null, ?string $endDate = null): array
    {
        return $this->dailyBuckets('{{%kickback_commissions}}', 'SUM(amount)', $startDate, $endDate);
    }

    /**
     * @return array<array{date: string, total: int}>
     */
    public function getDailyReferrals(?string $startDate = null, ?string $endDate = null): array
    {
        return $this->dailyBuckets('{{%kickback_referrals}}', 'COUNT(*)', $startDate, $endDate);
    }

    /**
     * @param Query<int, array<string, mixed>> $query
     * @return Query<int, array<string, mixed>>
     */
    public function applyDateFilter(Query $query, ?string $startDate, ?string $endDate): Query
    {
        if ($startDate !== null) {
            $query->andWhere(['>=', 'dateCreated', $startDate . ' 00:00:00']);
        }
        if ($endDate !== null) {
            $query->andWhere(['<=', 'dateCreated', $endDate . ' 23:59:59']);
        }

        return $query;
    }

    /**
     * @return array<array{date: string, total: float|int}>
     */
    private function dailyBuckets(string $table, string $aggregate, ?string $startDate, ?string $endDate): array
    {
        $chartStart = $startDate ?? (new \DateTime())->modify('-30 days')->format('Y-m-d');
        $chartEnd = $endDate ?? (new \DateTime())->format('Y-m-d');

        return (new Query())
            ->select(['DATE(dateCreated) as date', "$aggregate as total"])
            ->from($table)
            ->where(['between', 'dateCreated', $chartStart . ' 00:00:00', $chartEnd . ' 23:59:59'])
            ->groupBy(['DATE(dateCreated)'])
            ->orderBy(['date' => SORT_ASC])
            ->all();
    }
}
