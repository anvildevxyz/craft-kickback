<?php

declare(strict_types=1);

namespace anvildev\craftkickback\controllers;

use anvildev\craftkickback\elements\CommissionElement;
use anvildev\craftkickback\elements\ReferralElement;
use anvildev\craftkickback\helpers\CsvExportHelper;
use anvildev\craftkickback\KickBack;
use anvildev\craftkickback\models\Commission;
use anvildev\craftkickback\records\CommissionRecord;
use Craft;
use craft\web\Controller;
use yii\web\Response;

/**
 * Listing, approval workflow, and CSV export for affiliate commissions.
 */
class CommissionsController extends Controller
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        if (in_array((string)$action->id, ['approve', 'reject', 'reverse'], true)) {
            $this->requirePermission(KickBack::PERMISSION_APPROVE_COMMISSIONS);
            return true;
        }

        $this->requirePermission(KickBack::PERMISSION_MANAGE_COMMISSIONS);

        return true;
    }

    public function actionIndex(): Response
    {
        return $this->renderTemplate('kickback/commissions/index');
    }

    public function actionEdit(?int $commissionId = null): Response
    {
        if ($commissionId === null) {
            throw new \yii\web\NotFoundHttpException('Commission not found.');
        }

        $commission = CommissionElement::find()->id($commissionId)->one();

        if (!$commission instanceof CommissionElement) {
            throw new \yii\web\NotFoundHttpException('Commission not found.');
        }

        $plugin = KickBack::getInstance();

        $referral = $commission->referralId !== null
            ? ReferralElement::find()->id($commission->referralId)->one()
            : null;

        $affiliate = $commission->affiliateId !== null
            ? $plugin->affiliates->getAffiliateById($commission->affiliateId)
            : null;

        return $this->renderTemplate('kickback/commissions/_edit', [
            'commission' => $commission,
            'referral' => $referral,
            'affiliate' => $affiliate,
            'settings' => $plugin->getSettings(),
        ]);
    }

    public function actionApprove(): Response
    {
        return $this->handleStatusAction(
            'approveCommission',
            Craft::t('kickback', 'Commission approved.'),
            Craft::t('kickback', 'commission.message.approveFailed'),
        );
    }

    public function actionReject(): Response
    {
        return $this->handleStatusAction(
            'rejectCommission',
            Craft::t('kickback', 'Commission rejected.'),
            Craft::t('kickback', 'commission.message.rejectFailed'),
        );
    }

    public function actionReverse(): Response
    {
        return $this->handleStatusAction(
            'reverseCommission',
            Craft::t('kickback', 'Commission reversed.'),
            Craft::t('kickback', 'commission.message.reverseFailed'),
        );
    }

    private function handleStatusAction(string $serviceMethod, string $successMsg, string $errorMsg): Response
    {
        $this->requirePostRequest();
        $this->requirePermission(KickBack::PERMISSION_APPROVE_COMMISSIONS);

        $commissionId = (int)Craft::$app->getRequest()->getRequiredBodyParam('commissionId');
        $commission = KickBack::getInstance()->commissions->getCommissionById($commissionId);

        if ($commission === null) {
            throw new \yii\web\NotFoundHttpException('Commission not found.');
        }

        if (KickBack::getInstance()->commissions->$serviceMethod($commission)) {
            Craft::$app->getSession()->setNotice($successMsg);
        } else {
            Craft::$app->getSession()->setError($errorMsg);
        }

        return $this->redirectToPostedUrl();
    }

    public function actionExport(): void
    {
        $plugin = KickBack::getInstance();
        $status = Craft::$app->getRequest()->getQueryParam('status');
        $label = $status ?? 'all';

        CsvExportHelper::streamAsDownload(
            ['ID', 'Affiliate', 'Referral ID', 'Amount', 'Currency', 'Rate', 'Rate Type', 'Rule Applied', 'Tier', 'Status', 'Payout ID', 'Description', 'Date Created', 'Date Approved', 'Date Reversed'],
            function(int $offset, int $limit) use ($plugin, $status): array {
                $query = CommissionRecord::find()
                    ->orderBy(['dateCreated' => SORT_DESC])
                    ->offset($offset)
                    ->limit($limit);

                if ($status !== null && $status !== '' && in_array($status, Commission::STATUSES, true)) {
                    $query->where(['status' => $status]);
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
                    $commission->payoutId ?? '',
                    $commission->description ?? '',
                    $commission->dateCreated ?? '',
                    $commission->dateApproved ?? '',
                    $commission->dateReversed ?? '',
                ], $commissions);
            },
            'commissions-' . $label . '-' . date('Y-m-d') . '.csv',
        );
    }
}
