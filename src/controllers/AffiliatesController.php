<?php

declare(strict_types=1);

namespace anvildev\craftkickback\controllers;

use anvildev\craftkickback\elements\AffiliateElement;
use anvildev\craftkickback\helpers\CsvExportHelper;
use anvildev\craftkickback\KickBack;
use anvildev\craftkickback\records\AffiliateRecord;
use Craft;
use craft\web\Controller;
use yii\web\Response;

/**
 * CRUD and approval workflows for affiliate accounts.
 */
class AffiliatesController extends Controller
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
        return $this->renderTemplate('kickback/affiliates/index');
    }

    public function actionEdit(?int $affiliateId = null): Response
    {
        $affiliate = null;

        if ($affiliateId !== null) {
            $affiliate = KickBack::getInstance()->affiliates->getAffiliateById((int) $affiliateId);

            if ($affiliate === null) {
                throw new \yii\web\NotFoundHttpException('Affiliate not found.');
            }
        }

        $programs = KickBack::getInstance()->programs->getAllPrograms();

        $couponCreationAllowed = KickBack::getInstance()->getSettings()->enableCouponCreation;
        $couponCreationBlockReason = null;
        if ($couponCreationAllowed && $affiliate !== null && $affiliate->programId !== null) {
            $program = \anvildev\craftkickback\elements\ProgramElement::find()
                ->id($affiliate->programId)
                ->status(null)
                ->one();
            if ($program !== null && !$program->enableCouponCreation) {
                $couponCreationAllowed = false;
                $couponCreationBlockReason = Craft::t('kickback', 'Coupon creation is disabled for program "{name}".', [
                    'name' => $program->name,
                ]);
            }
        }

        $coupons = [];
        if ($affiliate !== null) {
            $kickbackCoupons = KickBack::getInstance()->coupons->getCouponsByAffiliateId($affiliate->id);
            $commerce = class_exists(\craft\commerce\Plugin::class)
                ? \craft\commerce\Plugin::getInstance()
                : null;
            foreach ($kickbackCoupons as $kbCoupon) {
                $commerceCoupon = $commerce?->getCoupons()->getCouponByCode($kbCoupon->code);
                $discount = $commerce?->getDiscounts()->getDiscountById($kbCoupon->discountId);
                $coupons[] = [
                    'id' => $kbCoupon->id,
                    'code' => $kbCoupon->code,
                    'dateCreated' => $kbCoupon->dateCreated,
                    'uses' => $commerceCoupon?->uses ?? 0,
                    'maxUses' => $commerceCoupon?->maxUses,
                    'enabled' => $discount?->enabled ?? true,
                ];
            }
        }

        return $this->renderTemplate('kickback/affiliates/_edit', [
            'affiliate' => $affiliate,
            'programs' => $programs,
            'coupons' => $coupons,
            'couponCreationAllowed' => $couponCreationAllowed,
            'couponCreationBlockReason' => $couponCreationBlockReason,
            'settings' => KickBack::getInstance()->getSettings(),
        ]);
    }

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $affiliateId = $request->getBodyParam('affiliateId');

        if ($affiliateId) {
            $affiliate = KickBack::getInstance()->affiliates->getAffiliateById((int) $affiliateId);

            if ($affiliate === null) {
                throw new \yii\web\NotFoundHttpException('Affiliate not found.');
            }
        } else {
            $affiliate = new AffiliateElement();
            $affiliate->affiliateStatus = AffiliateElement::STATUS_PENDING;
        }

        $affiliate->title = $request->getBodyParam('title', $affiliate->title);
        $affiliate->referralCode = $request->getBodyParam('referralCode', $affiliate->referralCode);

        // elementSelectField posts as userSelect[] - extract the first selected ID
        $userSelect = $request->getBodyParam('userSelect');
        if (is_array($userSelect) && !empty($userSelect)) {
            $affiliate->userId = (int) reset($userSelect);
        } elseif ($userSelect !== null && $userSelect !== '') {
            $affiliate->userId = (int) $userSelect;
        }
        $programId = $request->getBodyParam('programId');
        $affiliate->programId = $programId ? (int) $programId : $affiliate->programId;

        // affiliateStatus and commissionRateOverride are the authoritative
        // approval / financial levers and only writable by APPROVE_AFFILIATES
        // holders - MANAGE_AFFILIATES alone must not bypass the workflow.
        if (Craft::$app->getUser()->checkPermission(KickBack::PERMISSION_APPROVE_AFFILIATES)) {
            $affiliate->affiliateStatus = $request->getBodyParam('affiliateStatus', $affiliate->affiliateStatus);
        }

        $affiliate->payoutMethod = $request->getBodyParam('payoutMethod', $affiliate->payoutMethod);
        $affiliate->paypalEmail = $request->getBodyParam('paypalEmail', $affiliate->paypalEmail);
        $affiliate->stripeAccountId = $request->getBodyParam('stripeAccountId', $affiliate->stripeAccountId);
        $affiliate->payoutThreshold = (float)$request->getBodyParam('payoutThreshold', $affiliate->payoutThreshold);
        $affiliate->notes = $request->getBodyParam('notes', $affiliate->notes);

        if (Craft::$app->getUser()->checkPermission(KickBack::PERMISSION_APPROVE_AFFILIATES)) {
            $commissionRateOverride = $request->getBodyParam('commissionRateOverride');
            if ($commissionRateOverride !== null && $commissionRateOverride !== '') {
                $affiliate->commissionRateOverride = (float)$commissionRateOverride;
                $affiliate->commissionTypeOverride = $request->getBodyParam('commissionTypeOverride');
            } else {
                $affiliate->commissionRateOverride = null;
                $affiliate->commissionTypeOverride = null;
            }
        }

        $groupId = $request->getBodyParam('groupId');
        $affiliate->groupId = $groupId ? (int)$groupId : null;

        if (!Craft::$app->getElements()->saveElement($affiliate)) {
            Craft::$app->getSession()->setError(Craft::t('kickback', 'affiliate.message.saveFailed'));

            Craft::$app->getUrlManager()->setRouteParams([
                'affiliate' => $affiliate,
            ]);

            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('kickback', 'affiliate.message.saved'));

        return $this->redirectToPostedUrl($affiliate);
    }

    public function actionApprove(): Response
    {
        return $this->handleStatusAction(
            'approveAffiliate',
            Craft::t('kickback', 'affiliate.message.approved'),
            Craft::t('kickback', 'affiliate.message.approveFailed'),
        );
    }

    public function actionReject(): Response
    {
        return $this->handleStatusAction(
            'rejectAffiliate',
            Craft::t('kickback', 'affiliate.message.rejected'),
            Craft::t('kickback', 'affiliate.message.rejectFailed'),
        );
    }

    public function actionSuspend(): Response
    {
        return $this->handleStatusAction(
            'suspendAffiliate',
            Craft::t('kickback', 'Affiliate suspended.'),
            Craft::t('kickback', 'Couldn\'t suspend affiliate.'),
        );
    }

    public function actionReactivate(): Response
    {
        return $this->handleStatusAction(
            'reactivateAffiliate',
            Craft::t('kickback', 'Affiliate reactivated.'),
            Craft::t('kickback', 'Couldn\'t reactivate affiliate.'),
        );
    }

    /**
     * Shared pipeline for the four affiliate status transitions. Literal
     * messages are passed by the caller so translation extraction still sees them.
     */
    private function handleStatusAction(string $serviceMethod, string $successMsg, string $errorMsg): Response
    {
        $this->requirePostRequest();
        $this->requirePermission(KickBack::PERMISSION_APPROVE_AFFILIATES);

        $affiliateId = Craft::$app->getRequest()->getRequiredBodyParam('affiliateId');
        $affiliate = KickBack::getInstance()->affiliates->getAffiliateById((int) $affiliateId);

        if ($affiliate === null) {
            throw new \yii\web\NotFoundHttpException('Affiliate not found.');
        }

        if (KickBack::getInstance()->affiliates->$serviceMethod($affiliate)) {
            Craft::$app->getSession()->setNotice($successMsg);
        } else {
            Craft::$app->getSession()->setError($errorMsg);
        }

        return $this->redirectToPostedUrl();
    }

    /**
     * Bulk-generate N coupons for an affiliate. Delegates to
     * CouponService::bulkCreateAffiliateCoupons() which wraps the whole
     * batch in a single transaction so partial failure rolls everything
     * back. Flashes a notice or error and redirects back to the posted URL.
     */
    public function actionBulkGenerateCoupons(): ?Response
    {
        $this->requirePostRequest();

        if (!KickBack::getInstance()->getSettings()->enableCouponCreation) {
            Craft::$app->getSession()->setError(Craft::t('kickback', 'Coupon creation is disabled in plugin settings.'));
            return $this->redirectToPostedUrl();
        }

        $request = Craft::$app->getRequest();
        $affiliateId = (int)$request->getRequiredBodyParam('affiliateId');
        $prefix = (string)$request->getRequiredBodyParam('prefix');
        $count = (int)$request->getRequiredBodyParam('count');
        $discount = (float)($request->getBodyParam('discount') ?? 10.0);
        $maxUses = (int)($request->getBodyParam('maxUses') ?? 0);

        $affiliate = AffiliateElement::find()->id($affiliateId)->one();
        if (!$affiliate instanceof AffiliateElement) {
            Craft::$app->getSession()->setError(Craft::t('kickback', 'Affiliate not found.'));
            return $this->redirectToPostedUrl();
        }

        try {
            $created = KickBack::getInstance()->coupons->bulkCreateAffiliateCoupons(
                $affiliate,
                $prefix,
                $count,
                $discount,
                $maxUses,
            );
        } catch (\Throwable $e) {
            Craft::$app->getSession()->setError(Craft::t('kickback', 'Bulk generation failed: {message}', [
                'message' => $e->getMessage(),
            ]));
            return $this->redirectToPostedUrl();
        }

        Craft::$app->getSession()->setNotice(Craft::t('kickback', 'Created {count} coupons ({first} → {last}).', [
            'count' => count($created),
            'first' => $created[0]->code,
            'last' => $created[count($created) - 1]->code,
        ]));

        return $this->redirectToPostedUrl();
    }

    /**
     * Hard-delete a single coupon. Refuses if Commerce reports any uses,
     * since deleting a discount that's been redeemed could orphan order
     * history references. Use disableCoupon for those cases.
     */
    public function actionDeleteCoupon(): ?Response
    {
        $this->requirePostRequest();

        $couponId = (int)Craft::$app->getRequest()->getRequiredBodyParam('couponId');
        try {
            $deleted = KickBack::getInstance()->coupons->deleteCoupon($couponId);
        } catch (\Throwable $e) {
            Craft::$app->getSession()->setError($e->getMessage());
            return $this->redirectToPostedUrl();
        }

        if ($deleted) {
            Craft::$app->getSession()->setNotice(Craft::t('kickback', 'Coupon deleted.'));
        } else {
            Craft::$app->getSession()->setError(Craft::t('kickback', 'Coupon not found.'));
        }

        return $this->redirectToPostedUrl();
    }

    /**
     * Soft-disable a single coupon: sets the backing Commerce discount to
     * enabled=false. Preserves usage history.
     */
    public function actionDisableCoupon(): ?Response
    {
        $this->requirePostRequest();

        $couponId = (int)Craft::$app->getRequest()->getRequiredBodyParam('couponId');
        if (KickBack::getInstance()->coupons->disableCoupon($couponId)) {
            Craft::$app->getSession()->setNotice(Craft::t('kickback', 'Coupon disabled.'));
        } else {
            Craft::$app->getSession()->setError(Craft::t('kickback', 'Couldn\'t disable coupon.'));
        }

        return $this->redirectToPostedUrl();
    }

    /**
     * Bulk delete/disable a set of coupons by id. Operates on each coupon
     * independently so a single failure doesn't roll back the rest. Mode is
     * either "delete" (hard-delete unused) or "disable" (soft-disable). Used
     * coupons are skipped on delete with a per-coupon error in the session.
     */
    public function actionBulkCouponAction(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $couponIds = $request->getBodyParam('couponIds', []);
        $mode = (string)$request->getBodyParam('mode', 'delete');

        if (!is_array($couponIds) || empty($couponIds)) {
            Craft::$app->getSession()->setError(Craft::t('kickback', 'No coupons selected.'));
            return $this->redirectToPostedUrl();
        }

        $service = KickBack::getInstance()->coupons;
        $okCount = 0;
        $errors = [];

        foreach ($couponIds as $rawId) {
            $couponId = (int)$rawId;
            try {
                $ok = $mode === 'disable'
                    ? $service->disableCoupon($couponId)
                    : $service->deleteCoupon($couponId);
                if ($ok) {
                    $okCount++;
                } else {
                    $errors[] = "#{$couponId}: not found";
                }
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        $verb = $mode === 'disable' ? 'disabled' : 'deleted';
        if ($okCount > 0) {
            Craft::$app->getSession()->setNotice(Craft::t('kickback', "{count} coupon(s) {$verb}.", [
                'count' => $okCount,
            ]));
        }
        if (!empty($errors)) {
            Craft::$app->getSession()->setError(implode("\n", array_slice($errors, 0, 5)));
        }

        return $this->redirectToPostedUrl();
    }

    /**
     * Stream the affiliate's coupons as a CSV download. Includes live usage
     * counts pulled from Commerce so the export is meaningful for audits.
     */
    public function actionExportCoupons(): void
    {
        $affiliateId = (int)Craft::$app->getRequest()->getRequiredQueryParam('affiliateId');
        $affiliate = KickBack::getInstance()->affiliates->getAffiliateById($affiliateId);
        if ($affiliate === null) {
            throw new \yii\web\NotFoundHttpException('Affiliate not found.');
        }

        $kickbackCoupons = KickBack::getInstance()->coupons->getCouponsByAffiliateId($affiliateId);
        $commerce = class_exists(\craft\commerce\Plugin::class)
            ? \craft\commerce\Plugin::getInstance()
            : null;

        $rows = [];
        foreach ($kickbackCoupons as $kb) {
            $cc = $commerce?->getCoupons()->getCouponByCode($kb->code);
            $discount = $commerce?->getDiscounts()->getDiscountById($kb->discountId);
            $rows[] = [
                $kb->code,
                $cc?->uses ?? 0,
                $cc?->maxUses ?? '',
                $discount?->enabled ? 'enabled' : 'disabled',
                (string)$kb->dateCreated,
            ];
        }

        CsvExportHelper::streamAsDownload(
            ['Code', 'Uses', 'Max Uses', 'Status', 'Created'],
            function(int $offset, int $limit) use ($rows): array {
                return array_slice($rows, $offset, $limit);
            },
            sprintf('affiliate-%d-coupons-%s.csv', $affiliateId, date('Y-m-d')),
        );
    }

    /**
     * Stream affiliates as a CSV download, optionally filtered by status.
     */
    public function actionExport(): void
    {
        $plugin = KickBack::getInstance();
        $status = Craft::$app->getRequest()->getQueryParam('status');
        $label = $status ?? 'all';

        CsvExportHelper::streamAsDownload(
            ['ID', 'Name', 'Referral Code', 'Email', 'Program ID', 'Status', 'Payout Method', 'Pending Balance', 'Lifetime Earnings', 'Lifetime Referrals', 'Date Created'],
            function(int $offset, int $limit) use ($plugin, $status): array {
                $query = AffiliateRecord::find()
                    ->orderBy(['dateCreated' => SORT_DESC])
                    ->offset($offset)
                    ->limit($limit);

                if ($status !== null && $status !== '') {
                    $query->where(['status' => $status]);
                }

                /** @var AffiliateRecord[] $records */
                $records = $query->all();
                if ($records === []) {
                    return [];
                }

                $affiliateIds = array_map(fn($r) => $r->id, $records);
                $affiliates = $plugin->affiliates->getAffiliatesByIds($affiliateIds);

                return array_map(fn($record) => [
                    $record->id,
                    ($affiliates[$record->id] ?? null)?->title ?? '',
                    $record->referralCode,
                    ($affiliates[$record->id] ?? null)?->getUser()?->email ?? '',
                    $record->programId,
                    $record->status,
                    $record->payoutMethod,
                    $record->pendingBalance,
                    $record->lifetimeEarnings,
                    $record->lifetimeReferrals,
                    $record->dateCreated,
                ], $records);
            },
            'affiliates-' . $label . '-' . date('Y-m-d') . '.csv',
        );
    }
}
