<?php

declare(strict_types=1);

namespace anvildev\craftkickback\controllers;

use anvildev\craftkickback\helpers\CsvExportHelper;
use anvildev\craftkickback\jobs\BatchPayoutJob;
use anvildev\craftkickback\KickBack;
use Craft;
use craft\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Payout management: creation, processing, batch runs, and CSV export.
 */
class PayoutsController extends Controller
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission(KickBack::PERMISSION_MANAGE_PAYOUTS);

        return true;
    }

    public function actionIndex(): Response
    {
        return $this->renderTemplate('kickback/payouts/index');
    }

    public function actionExport(?string $status = null): Response
    {
        $payouts = KickBack::getInstance()->payouts->getAllPayouts($status);

        $affiliateIds = array_map(fn($p) => $p->affiliateId, $payouts);
        $affiliates = KickBack::getInstance()->affiliates->getAffiliatesByIds($affiliateIds);

        $rows = array_map(function($payout) use ($affiliates) {
            $affiliate = $affiliates[$payout->affiliateId] ?? null;
            $user = $affiliate?->getUser();

            return [
                $payout->id,
                $affiliate?->title ?? '',
                $user?->email ?? '',
                $affiliate?->referralCode ?? '',
                $payout->amount,
                $payout->currency,
                $payout->method,
                $affiliate?->paypalEmail ?? '',
                $affiliate?->stripeAccountId ?? '',
                $payout->status,
                $payout->transactionId ?? '',
                $payout->notes ?? '',
                $payout->dateCreated ? $payout->dateCreated->format('Y-m-d H:i:s') : '',
                $payout->processedAt ? $payout->processedAt->format('Y-m-d H:i:s') : '',
            ];
        }, $payouts);

        $label = $status ?? 'all';

        return CsvExportHelper::sendAsDownload(
            ['ID', 'Affiliate', 'Email', 'Referral Code', 'Amount', 'Currency', 'Method', 'PayPal Email', 'Stripe Account ID', 'Status', 'Transaction ID', 'Notes', 'Date Created', 'Date Processed'],
            $rows,
            'payouts-' . $label . '-' . date('Y-m-d') . '.csv',
        );
    }

    public function actionView(int $payoutId): Response
    {
        $plugin = KickBack::getInstance();
        $payout = $plugin->payouts->getPayoutById($payoutId);

        if ($payout === null) {
            throw new NotFoundHttpException('Payout not found.');
        }

        $gateway = $plugin->payoutGateways->getGateway($payout->method);

        return $this->renderTemplate('kickback/payouts/_view', [
            'payout' => $payout,
            'affiliate' => $plugin->affiliates->getAffiliateById($payout->affiliateId),
            'gatewayAvailable' => $gateway !== null && $gateway->isConfigured(),
            'gatewayName' => $gateway?->getDisplayName(),
        ]);
    }

    public function actionCreate(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission(KickBack::PERMISSION_PROCESS_PAYOUTS);

        $affiliateId = Craft::$app->getRequest()->getRequiredBodyParam('affiliateId');
        $notes = Craft::$app->getRequest()->getBodyParam('notes');

        $affiliate = KickBack::getInstance()->affiliates->getAffiliateById((int)$affiliateId);
        if ($affiliate === null) {
            throw new NotFoundHttpException('Affiliate not found.');
        }

        $payout = KickBack::getInstance()->payouts->createPayout($affiliate, $notes);

        if ($payout === null) {
            Craft::$app->getSession()->setError(Craft::t('kickback', 'payout.message.createFailed'));
            return $this->redirectToPostedUrl();
        }

        Craft::$app->getSession()->setNotice(Craft::t('kickback', 'payout.message.created'));

        return $this->redirectToPostedUrl();
    }

    public function actionComplete(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission(KickBack::PERMISSION_PROCESS_PAYOUTS);

        $request = Craft::$app->getRequest();
        $transactionId = $request->getBodyParam('transactionId');
        $payout = $this->requirePayoutFromBody();
        return $this->runPayoutMutation(
            fn() => KickBack::getInstance()->payouts->completePayout($payout, $transactionId),
            Craft::t('kickback', 'payout.message.completed'),
            Craft::t('kickback', 'payout.message.completeFailed'),
        );
    }

    public function actionFail(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission(KickBack::PERMISSION_PROCESS_PAYOUTS);

        $request = Craft::$app->getRequest();
        $notes = $request->getBodyParam('notes');
        $payout = $this->requirePayoutFromBody();
        return $this->runPayoutMutation(
            fn() => KickBack::getInstance()->payouts->failPayout($payout, $notes),
            Craft::t('kickback', 'payout.message.failed'),
            Craft::t('kickback', 'payout.message.updateFailed'),
        );
    }

    public function actionCancel(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission(KickBack::PERMISSION_PROCESS_PAYOUTS);

        $payout = $this->requirePayoutFromBody();
        return $this->runPayoutMutation(
            fn() => KickBack::getInstance()->payouts->cancelPayout($payout),
            Craft::t('kickback', 'payout.message.cancelled'),
            Craft::t('kickback', 'payout.message.cancelFailed'),
        );
    }

    public function actionProcess(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission(KickBack::PERMISSION_PROCESS_PAYOUTS);

        $payout = $this->requirePayoutFromBody();
        return $this->runPayoutMutation(
            fn() => KickBack::getInstance()->payouts->processPayout($payout),
            Craft::t('kickback', 'Payout processed via {method}.', [
                'method' => ucfirst($payout->method),
            ]),
            Craft::t('kickback', 'payout.message.processFailed'),
        );
    }

    public function actionBatch(): Response
    {
        $this->requirePermission(KickBack::PERMISSION_PROCESS_PAYOUTS);

        $plugin = KickBack::getInstance();

        return $this->renderTemplate('kickback/payouts/batch', [
            'eligibleAffiliates' => $plugin->payouts->getEligibleAffiliates(),
            'minimumAmount' => $plugin->getSettings()->minimumPayoutAmount,
            'currency' => KickBack::getCommerceCurrency(),
            'hasConfiguredGateways' => !empty($plugin->payoutGateways->getConfiguredGateways()),
        ]);
    }

    public function actionProcessBatch(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission(KickBack::PERMISSION_PROCESS_PAYOUTS);

        $request = Craft::$app->getRequest();
        $notes = $request->getBodyParam('notes');
        $autoProcess = (bool)$request->getBodyParam('autoProcess');

        Craft::$app->getQueue()->push(new BatchPayoutJob([
            'notes' => $notes,
            'autoProcess' => $autoProcess,
        ]));

        Craft::$app->getSession()->setNotice(
            Craft::t('kickback', 'Batch payout job queued. Check the queue for progress.')
        );

        return $this->redirect('kickback/payouts');
    }

    private function requirePayoutFromBody(): \anvildev\craftkickback\elements\PayoutElement
    {
        $payoutId = (int)Craft::$app->getRequest()->getRequiredBodyParam('payoutId');
        $payout = KickBack::getInstance()->payouts->getPayoutById($payoutId);
        if ($payout === null) {
            throw new NotFoundHttpException('Payout not found.');
        }

        return $payout;
    }

    private function runPayoutMutation(callable $mutation, string $successMessage, string $errorMessage): Response
    {
        if ($mutation()) {
            Craft::$app->getSession()->setNotice($successMessage);
        } else {
            Craft::$app->getSession()->setError($errorMessage);
        }

        return $this->redirectToPostedUrl();
    }
}
