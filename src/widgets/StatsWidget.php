<?php

declare(strict_types=1);

namespace anvildev\craftkickback\widgets;

use anvildev\craftkickback\elements\AffiliateElement;
use anvildev\craftkickback\KickBack;
use anvildev\craftkickback\models\Commission;
use anvildev\craftkickback\models\Referral;
use Craft;
use craft\base\Widget;
use craft\db\Query;

class StatsWidget extends Widget
{
    public static function displayName(): string
    {
        return Craft::t('kickback', 'Kickback Stats');
    }

    public static function icon(): ?string
    {
        return 'chart-bar';
    }

    public static function isSelectable(): bool
    {
        $user = Craft::$app->getUser();

        return $user->getIsAdmin()
            || $user->checkPermission(KickBack::PERMISSION_VIEW_REPORTS)
            || $user->checkPermission(KickBack::PERMISSION_MANAGE_AFFILIATES);
    }

    public function getBodyHtml(): ?string
    {
        $activeAffiliates = (int) AffiliateElement::find()
            ->siteId('*')
            ->affiliateStatus(AffiliateElement::STATUS_ACTIVE)
            ->count();

        $thirtyDaysAgo = (new \DateTime())->modify('-30 days')->format('Y-m-d') . ' 00:00:00';

        $recentReferrals = (int) (new Query())
            ->from('{{%kickback_referrals}}')
            ->where(['>=', 'dateCreated', $thirtyDaysAgo])
            ->count();

        $recentCommissions = (float) ((new Query())
            ->from('{{%kickback_commissions}}')
            ->where(['>=', 'dateCreated', $thirtyDaysAgo])
            ->andWhere(['status' => Commission::STATUS_APPROVED])
            ->sum('amount') ?? 0);

        $pendingBalance = (float) ((new Query())
            ->from('{{%kickback_commissions}}')
            ->where(['status' => Commission::STATUS_PENDING])
            ->sum('amount') ?? 0);

        $flaggedCount = (int) (new Query())
            ->from('{{%kickback_referrals}}')
            ->where(['status' => Referral::STATUS_FLAGGED])
            ->count();

        Craft::$app->getView()->registerCss(<<<'CSS'
            .kb-widget { font-size: 13px; }
            .kb-widget__row { display: flex; gap: 1rem; margin-bottom: 0.75rem; }
            .kb-widget__stat { flex: 1; }
            .kb-widget__value { display: block; font-size: 1.25rem; font-weight: 600; line-height: 1.2; }
            .kb-widget__label { display: block; font-size: 11px; color: var(--gray-550); margin-top: 2px; }
            .kb-widget__alert { background: var(--red-050); color: var(--red-600); padding: 6px 10px; border-radius: 4px; margin-bottom: 0.75rem; font-size: 12px; }
            .kb-widget__alert a { color: inherit; text-decoration: underline; }
            .kb-widget__footer { border-top: 1px solid var(--gray-200); padding-top: 0.5rem; font-size: 12px; }
            .kb-widget__footer a { color: var(--link-color); }
            CSS);

        return Craft::$app->getView()->renderTemplate('kickback/widgets/stats', [
            'activeAffiliates' => $activeAffiliates,
            'recentReferrals' => $recentReferrals,
            'recentCommissions' => $recentCommissions,
            'pendingBalance' => $pendingBalance,
            'flaggedCount' => $flaggedCount,
            'currency' => KickBack::getCommerceCurrency(),
        ]);
    }
}
