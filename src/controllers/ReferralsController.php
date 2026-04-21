<?php

declare(strict_types=1);

namespace anvildev\craftkickback\controllers;

use anvildev\craftkickback\elements\ReferralElement;
use anvildev\craftkickback\helpers\CsvExportHelper;
use anvildev\craftkickback\KickBack;
use anvildev\craftkickback\models\Commission;
use anvildev\craftkickback\models\Referral;
use anvildev\craftkickback\records\ReferralRecord;
use Craft;
use craft\web\Controller;
use yii\web\Response;

/**
 * Listing, approval workflow, and CSV export for affiliate referrals.
 */
class ReferralsController extends Controller
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        if (in_array((string)$action->id, ['approve', 'reject'], true)) {
            $this->requirePermission(KickBack::PERMISSION_APPROVE_REFERRALS);
            return true;
        }

        $this->requirePermission(KickBack::PERMISSION_MANAGE_REFERRALS);

        return true;
    }

    public function actionIndex(): Response
    {
        return $this->renderTemplate('kickback/referrals/index');
    }

    public function actionEdit(?int $referralId = null): Response
    {
        if ($referralId === null) {
            throw new \yii\web\NotFoundHttpException('Referral not found.');
        }

        $referral = ReferralElement::find()->id($referralId)->one();

        if (!$referral instanceof ReferralElement) {
            throw new \yii\web\NotFoundHttpException('Referral not found.');
        }

        $plugin = KickBack::getInstance();
        $affiliate = $referral->affiliateId !== null
            ? $plugin->affiliates->getAffiliateById($referral->affiliateId)
            : null;

        return $this->renderTemplate('kickback/referrals/_edit', [
            'referral' => $referral,
            'affiliate' => $affiliate,
            'settings' => $plugin->getSettings(),
        ]);
    }

    public function actionApprove(): Response
    {
        return $this->handleStatusAction(
            Referral::STATUS_APPROVED,
            'approveReferral',
            'approveCommission',
            'Referral approved.',
            'Couldn\'t approve referral.',
            'approved',
        );
    }

    public function actionReject(): Response
    {
        return $this->handleStatusAction(
            Referral::STATUS_REJECTED,
            'rejectReferral',
            'rejectCommission',
            'Referral rejected.',
            'Couldn\'t reject referral.',
            'rejected',
        );
    }

    public function actionExport(): void
    {
        $plugin = KickBack::getInstance();
        $status = Craft::$app->getRequest()->getQueryParam('status');
        $label = $status ?? 'all';

        CsvExportHelper::streamAsDownload(
            ['ID', 'Affiliate', 'Program ID', 'Order ID', 'Customer Email', 'Order Subtotal', 'Status', 'Attribution Method', 'Coupon Code', 'Date Created', 'Date Approved'],
            function(int $offset, int $limit) use ($plugin, $status): array {
                $query = ReferralRecord::find()
                    ->orderBy(['dateCreated' => SORT_DESC])
                    ->offset($offset)
                    ->limit($limit);

                if ($status !== null && $status !== '' && in_array($status, Referral::STATUSES, true)) {
                    $query->where(['status' => $status]);
                }

                /** @var ReferralRecord[] $referrals */
                $referrals = $query->all();
                if (empty($referrals)) {
                    return [];
                }

                $affiliateIds = array_map(fn($r) => $r->affiliateId, $referrals);
                $affiliates = $plugin->affiliates->getAffiliatesByIds($affiliateIds);

                return array_map(fn($referral) => [
                    $referral->id,
                    ($affiliates[$referral->affiliateId] ?? null)?->title ?? '',
                    $referral->programId,
                    $referral->orderId ?? '',
                    $referral->customerEmail ?? '',
                    $referral->orderSubtotal,
                    $referral->status,
                    $referral->attributionMethod,
                    $referral->couponCode ?? '',
                    $referral->dateCreated ?? '',
                    $referral->dateApproved ?? '',
                ], $referrals);
            },
            'referrals-' . $label . '-' . date('Y-m-d') . '.csv',
        );
    }

    private function handleStatusAction(
        string $targetStatus,
        string $referralMethod,
        string $commissionMethod,
        string $successMsg,
        string $errorMsg,
        string $logVerb,
    ): Response {
        $this->requirePostRequest();
        $this->requirePermission(KickBack::PERMISSION_APPROVE_REFERRALS);

        $plugin = KickBack::getInstance();
        $referralId = (int)Craft::$app->getRequest()->getRequiredBodyParam('referralId');
        $referral = $plugin->referrals->getReferralById($referralId);

        if ($referral === null) {
            throw new \yii\web\NotFoundHttpException('Referral not found.');
        }

        if ($referral->status === $targetStatus) {
            Craft::$app->getSession()->setNotice(
                Craft::t('kickback', 'Referral #{id} is already {status}.', [
                    'id' => $referral->id,
                    'status' => $targetStatus,
                ]),
            );
            return $this->redirectToPostedUrl();
        }

        if (!in_array($referral->status, [Referral::STATUS_PENDING, Referral::STATUS_FLAGGED], true)) {
            Craft::$app->getSession()->setError(
                Craft::t('kickback', 'Referral #{id} cannot be {status} from its current state ({currentStatus}).', [
                    'id' => $referral->id,
                    'status' => $targetStatus,
                    'currentStatus' => $referral->status,
                ])
            );
            return $this->redirectToPostedUrl();
        }

        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            if (!$plugin->referrals->$referralMethod($referral)) {
                $transaction->rollBack();
                Craft::$app->getSession()->setError(Craft::t('kickback', $errorMsg));
                return $this->redirectToPostedUrl();
            }

            $commissions = $plugin->commissions->getCommissionsByReferralId($referral->id);
            foreach ($commissions as $commission) {
                if ($commission->status === Commission::STATUS_PENDING) {
                    $plugin->commissions->$commissionMethod($commission);
                }
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        $userId = Craft::$app->getUser()->getIdentity()?->id ?? 0;
        Craft::info(
            "Referral #{$referral->id} {$logVerb} by user #{$userId} (affiliate #{$referral->affiliateId}, "
            . count($commissions) . " commission(s) cascaded)",
            __METHOD__,
        );

        Craft::$app->getSession()->setNotice(Craft::t('kickback', $successMsg));
        return $this->redirectToPostedUrl();
    }
}
