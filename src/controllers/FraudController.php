<?php

declare(strict_types=1);

namespace anvildev\craftkickback\controllers;

use anvildev\craftkickback\helpers\CsvExportHelper;
use anvildev\craftkickback\KickBack;
use anvildev\craftkickback\records\ReferralRecord;
use Craft;
use craft\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Fraud review: list + export. Per-referral approve/reject lives on ReferralElement
 * via element actions on the "status:flagged" source.
 */
class FraudController extends Controller
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $canManage = Craft::$app->getUser()->checkPermission(KickBack::PERMISSION_MANAGE_REFERRALS);
        $canApprove = Craft::$app->getUser()->checkPermission(KickBack::PERMISSION_APPROVE_REFERRALS);
        if (!$canManage && !$canApprove) {
            throw new ForbiddenHttpException('User is not permitted to perform this action.');
        }

        return true;
    }

    public function actionIndex(): Response
    {
        return $this->renderTemplate('kickback/fraud/index');
    }

    public function actionExport(): void
    {
        CsvExportHelper::streamAsDownload(
            ['Referral ID', 'Affiliate ID', 'Order ID', 'Customer Email', 'Status', 'Fraud Flags', 'Date Created'],
            function(int $offset, int $limit): array {
                /** @var ReferralRecord[] $records */
                $records = ReferralRecord::find()
                    ->where(['not', ['fraudFlags' => null]])
                    ->orderBy(['dateCreated' => SORT_DESC])
                    ->offset($offset)
                    ->limit($limit)
                    ->all();

                return array_map(fn($r) => [
                    $r->id,
                    $r->affiliateId,
                    $r->orderId,
                    $r->customerEmail,
                    $r->status,
                    $r->fraudFlags,
                    $r->dateCreated,
                ], $records);
            },
            'fraud-flags-' . date('Y-m-d') . '.csv',
        );
    }
}
