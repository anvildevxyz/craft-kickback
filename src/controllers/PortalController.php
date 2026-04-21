<?php

declare(strict_types=1);

namespace anvildev\craftkickback\controllers;

use anvildev\craftkickback\elements\AffiliateElement;
use anvildev\craftkickback\KickBack;
use anvildev\craftkickback\models\Commission;
use anvildev\craftkickback\models\Referral;
use Craft;
use craft\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Front-end affiliate portal: dashboard, referrals, commissions,
 * coupons, and payout settings.
 */
class PortalController extends Controller
{
    /**
     * Allow anonymous so Craft's base beforeAction() doesn't throw a bare 403
     * for site requests. We handle the login redirect ourselves below.
     */
    protected array|bool|int $allowAnonymous = self::ALLOW_ANONYMOUS_LIVE;

    private ?AffiliateElement $_affiliate = null;

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        if (Craft::$app->getUser()->getIsGuest()) {
            $loginPath = Craft::$app->getConfig()->getGeneral()->getLoginPath();
            if ($loginPath) {
                Craft::$app->getUser()->setReturnUrl(Craft::$app->getRequest()->getAbsoluteUrl());
                $this->redirect('/' . $loginPath);
                return false;
            }
            throw new ForbiddenHttpException('Login Required');
        }

        $user = Craft::$app->getUser()->getIdentity();
        $this->_affiliate = KickBack::getInstance()->affiliates->getAffiliateByUserId($user->id);

        if ($this->_affiliate === null) {
            $this->redirect(
                '/' . KickBack::getInstance()->getSettings()->getCurrentSitePortalPath() . '/register'
            );
            return false;
        }

        if ($this->_affiliate->affiliateStatus === AffiliateElement::STATUS_PENDING) {
            if ($action->id !== 'pending') {
                $this->redirect(
                    '/' . KickBack::getInstance()->getSettings()->getCurrentSitePortalPath() . '/pending'
                );
                return false;
            }
        }

        if (in_array($this->_affiliate->affiliateStatus, [AffiliateElement::STATUS_SUSPENDED, AffiliateElement::STATUS_REJECTED], true)) {
            throw new ForbiddenHttpException('Your affiliate account is not active.');
        }

        return true;
    }

    public function actionDashboard(): Response
    {
        $plugin = KickBack::getInstance();
        $affiliateId = $this->_affiliate->id;

        $recentReferrals = $plugin->referrals->getReferralsByAffiliateId($affiliateId, null, 5);
        $totalReferralCount = $plugin->referrals->countReferralsByAffiliateId($affiliateId);

        $recentCommissions = $plugin->commissions->getCommissionsByAffiliateId($affiliateId, null, 5);
        $totalCommissionCount = $plugin->commissions->countCommissionsByAffiliateId($affiliateId);

        $clickCount = \anvildev\craftkickback\records\ClickRecord::find()
            ->where(['affiliateId' => $affiliateId])
            ->count();

        return $this->renderTemplate('kickback/portal/dashboard', [
            'affiliate' => $this->_affiliate,
            'recentReferrals' => $recentReferrals,
            'totalReferralCount' => $totalReferralCount,
            'recentCommissions' => $recentCommissions,
            'totalCommissionCount' => $totalCommissionCount,
            'clickCount' => $clickCount,
            'settings' => $plugin->getSettings(),
        ]);
    }

    public function actionPending(): Response
    {
        if ($this->_affiliate->affiliateStatus !== AffiliateElement::STATUS_PENDING) {
            return $this->redirect(
                '/' . KickBack::getInstance()->getSettings()->getCurrentSitePortalPath()
            );
        }

        return $this->renderTemplate('kickback/portal/pending', [
            'affiliate' => $this->_affiliate,
        ]);
    }

    public function actionLinks(): Response
    {
        return $this->renderTemplate('kickback/portal/links', [
            'affiliate' => $this->_affiliate,
            'settings' => KickBack::getInstance()->getSettings(),
        ]);
    }

    public function actionCommissions(): Response
    {
        $request = Craft::$app->getRequest();
        $plugin = KickBack::getInstance();

        $status = $request->getQueryParam('status');
        if ($status !== null && !in_array($status, Commission::STATUSES, true)) {
            $status = null;
        }

        $perPage = 25;
        $page = max(1, (int)$request->getQueryParam('page', 1));
        $offset = ($page - 1) * $perPage;

        $total = $plugin->commissions->countCommissionsByAffiliateId(
            $this->_affiliate->id,
            $status,
        );

        $commissions = $plugin->commissions->getCommissionsByAffiliateId(
            $this->_affiliate->id,
            $status,
            $perPage,
            $offset,
        );

        return $this->renderTemplate('kickback/portal/commissions', [
            'affiliate' => $this->_affiliate,
            'commissions' => $commissions,
            'currentStatus' => $status,
            'statuses' => Commission::STATUSES,
            'settings' => $plugin->getSettings(),
            'currentPage' => $page,
            'totalPages' => max(1, (int)ceil($total / $perPage)),
            'totalCount' => $total,
        ]);
    }

    public function actionReferrals(): Response
    {
        $request = Craft::$app->getRequest();
        $plugin = KickBack::getInstance();

        $status = $request->getQueryParam('status');
        if ($status !== null && !in_array($status, Referral::STATUSES, true)) {
            $status = null;
        }

        $subId = is_string($raw = $request->getQueryParam('subid')) && ($raw = trim($raw)) !== '' ? $raw : null;

        $perPage = 25;
        $page = max(1, (int)$request->getQueryParam('page', 1));
        $offset = ($page - 1) * $perPage;

        $total = $plugin->referrals->countReferralsByAffiliateId(
            $this->_affiliate->id,
            $status,
            $subId,
        );

        $referrals = $plugin->referrals->getReferralsByAffiliateId(
            $this->_affiliate->id,
            $status,
            $perPage,
            $offset,
            $subId,
        );

        return $this->renderTemplate('kickback/portal/referrals', [
            'affiliate' => $this->_affiliate,
            'referrals' => $referrals,
            'currentStatus' => $status,
            'currentSubId' => $subId,
            'statuses' => Referral::STATUSES,
            'settings' => $plugin->getSettings(),
            'currentPage' => $page,
            'totalPages' => max(1, (int)ceil($total / $perPage)),
            'totalCount' => $total,
        ]);
    }

    public function actionTeam(): Response
    {
        $plugin = KickBack::getInstance();
        $settings = $plugin->getSettings();

        if (!$settings->enableMultiTier) {
            throw new \yii\web\NotFoundHttpException('Multi-tier referral is not enabled.');
        }

        $downline = $plugin->affiliates->getAffiliatesByParentId($this->_affiliate->id);

        $baseUrl = rtrim(Craft::$app->getSites()->getCurrentSite()->getBaseUrl() ?? '/', '/');
        $portalPath = $settings->getCurrentSitePortalPath() ?? '';
        $recruitUrl = $baseUrl . '/' . $portalPath
            . '/register?recruiter=' . urlencode($this->_affiliate->referralCode);

        return $this->renderTemplate('kickback/portal/team', [
            'affiliate' => $this->_affiliate,
            'downline' => $downline,
            'recruitUrl' => $recruitUrl,
            'settings' => $settings,
        ]);
    }

    public function actionCoupons(): Response
    {
        $plugin = KickBack::getInstance();
        $records = $plugin->coupons->getCouponsByAffiliateId($this->_affiliate->id);

        $commerce = class_exists(\craft\commerce\Plugin::class)
            ? \craft\commerce\Plugin::getInstance()
            : null;

        $activeCoupons = [];
        $inactiveCoupons = [];

        foreach ($records as $record) {
            $discount = $commerce?->getDiscounts()->getDiscountById($record->discountId);
            $commerceCoupon = $commerce?->getCoupons()->getCouponByCode($record->code);

            $couponData = [
                'record' => $record,
                'enabled' => $discount?->enabled ?? false,
                'uses' => $commerceCoupon->uses ?? 0,
                'maxUses' => $commerceCoupon->maxUses ?? null,
                'discountPercent' => $discount !== null
                    ? abs($discount->percentDiscount) * 100
                    : null,
            ];

            if ($couponData['enabled']) {
                $activeCoupons[] = $couponData;
            } else {
                $inactiveCoupons[] = $couponData;
            }
        }

        return $this->renderTemplate('kickback/portal/coupons', [
            'affiliate' => $this->_affiliate,
            'coupons' => $records,
            'activeCoupons' => $activeCoupons,
            'inactiveCoupons' => $inactiveCoupons,
            'settings' => $plugin->getSettings(),
        ]);
    }

    public function actionGenerateCoupon(): ?Response
    {
        $this->requirePostRequest();

        $plugin = KickBack::getInstance();
        $settings = $plugin->getSettings();
        $couponsUrl = '/' . $settings->getCurrentSitePortalPath() . '/coupons';

        if (!$settings->enableCouponCreation || !$settings->allowAffiliateSelfServiceCoupons) {
            throw new ForbiddenHttpException('Affiliate self-service coupon generation is disabled.');
        }

        $maxCoupons = $settings->maxCouponsPerAffiliate;
        $maxPercent = $settings->maxSelfServiceDiscountPercent;
        $db = Craft::$app->getDb();

        // FOR UPDATE lock on the affiliate row serialises coupon creation so
        // the per-affiliate cap holds under concurrent submits.
        $transaction = $db->beginTransaction();
        try {
            $affiliateId = (int)$this->_affiliate->id;
            $db->createCommand(
                'SELECT [[id]] FROM {{%kickback_affiliates}} WHERE [[id]] = :id FOR UPDATE',
                [':id' => $affiliateId],
            )->queryScalar();

            $existingCount = count($plugin->coupons->getCouponsByAffiliateId($affiliateId));
            if ($existingCount >= $maxCoupons) {
                $transaction->rollBack();
                Craft::$app->getSession()->setError(
                    Craft::t('kickback', 'You have reached the maximum number of coupons ({max}).', [
                        'max' => $maxCoupons,
                    ])
                );
                return $this->redirect($couponsUrl);
            }

            $request = Craft::$app->getRequest();
            $discountPercent = (float)($request->getBodyParam('discountPercent') ?? 10);
            $discountPercent = max(1, min($maxPercent, $discountPercent));

            $code = $plugin->coupons->generateCouponCode($this->_affiliate);

            try {
                $coupon = $plugin->coupons->createAffiliateCoupon(
                    $this->_affiliate,
                    $code,
                    $discountPercent,
                );
            } catch (\RuntimeException $e) {
                $transaction->rollBack();
                Craft::$app->getSession()->setError($e->getMessage());
                return $this->redirect($couponsUrl);
            }

            if ($coupon === null) {
                $transaction->rollBack();
                Craft::$app->getSession()->setError(
                    Craft::t('kickback', 'coupon.message.createFailed')
                );
                return $this->redirect($couponsUrl);
            }

            $transaction->commit();

            Craft::$app->getSession()->setNotice(
                Craft::t('kickback', 'Coupon "{code}" created.', ['code' => $code])
            );
            return $this->redirect($couponsUrl);
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    public function actionSettings(): Response
    {
        $plugin = KickBack::getInstance();
        $stripeGateway = $plugin->payoutGateways->getStripeGateway();
        $stripeConfigured = $stripeGateway !== null && $stripeGateway->isConfigured();
        $stripeReady = false;

        if ($stripeConfigured && !empty($this->_affiliate->stripeAccountId)) {
            $stripeReady = $stripeGateway->isAccountReady($this->_affiliate->stripeAccountId);
        }

        return $this->renderTemplate('kickback/portal/settings', [
            'affiliate' => $this->_affiliate,
            'settings' => $plugin->getSettings(),
            'currency' => KickBack::getCommerceCurrency(),
            'stripeConfigured' => $stripeConfigured,
            'stripeReady' => $stripeReady,
        ]);
    }

    public function actionStripeOnboard(): Response
    {
        $this->requirePostRequest();

        $plugin = KickBack::getInstance();
        $stripeGateway = $plugin->payoutGateways->getStripeGateway();
        $settings = $plugin->getSettings();
        $portalPath = '/' . $settings->getCurrentSitePortalPath();

        if ($stripeGateway === null || !$stripeGateway->isConfigured()) {
            Craft::$app->getSession()->setError(Craft::t('kickback', 'Stripe payouts are not currently available.'));
            return $this->redirect($portalPath . '/settings');
        }

        $affiliate = $this->_affiliate;

        if (empty($affiliate->stripeAccountId)) {
            $accountId = $stripeGateway->createConnectedAccount($affiliate);
            if ($accountId === null) {
                Craft::$app->getSession()->setError(Craft::t('kickback', 'portal.stripe.message.accountCreateFailed'));
                return $this->redirect($portalPath . '/settings');
            }

            $affiliate->stripeAccountId = $accountId;
            Craft::$app->getElements()->saveElement($affiliate);
        }

        $siteUrl = Craft::$app->getSites()->getCurrentSite()->getBaseUrl();
        $refreshUrl = rtrim($siteUrl, '/') . $portalPath . '/settings';
        $returnUrl = $refreshUrl;

        $onboardingUrl = $stripeGateway->createOnboardingLink(
            $affiliate->stripeAccountId,
            $refreshUrl,
            $returnUrl,
        );

        if ($onboardingUrl === null) {
            Craft::$app->getSession()->setError(Craft::t('kickback', 'portal.stripe.message.onboardingFailed'));
            return $this->redirect($portalPath . '/settings');
        }

        return $this->redirect($onboardingUrl);
    }

    public function actionSaveSettings(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $affiliate = $this->_affiliate;

        $affiliate->payoutMethod = $request->getBodyParam('payoutMethod', $affiliate->payoutMethod);
        $affiliate->paypalEmail = $request->getBodyParam('paypalEmail', $affiliate->paypalEmail);

        $threshold = (float)$request->getBodyParam('payoutThreshold', $affiliate->payoutThreshold);
        $minimumPayout = KickBack::getInstance()->getSettings()->minimumPayoutAmount;
        $affiliate->payoutThreshold = max($threshold, $minimumPayout);

        if (!Craft::$app->getElements()->saveElement($affiliate)) {
            if ($request->getAcceptsJson()) {
                return $this->asJson(['success' => false, 'errors' => $affiliate->getErrors()]);
            }

            Craft::$app->getSession()->setError(Craft::t('kickback', 'settings.message.saveFailed'));
            Craft::$app->getUrlManager()->setRouteParams(['affiliate' => $affiliate]);

            return null;
        }

        if ($request->getAcceptsJson()) {
            return $this->asJson(['success' => true]);
        }

        Craft::$app->getSession()->setNotice(Craft::t('kickback', 'settings.message.saved'));

        return $this->redirectToPostedUrl();
    }

    public function actionRequestPayout(): ?Response
    {
        $this->requirePostRequest();

        $plugin = KickBack::getInstance();
        $settings = $plugin->getSettings();
        $affiliate = $this->_affiliate;

        if ($affiliate->pendingBalance < $settings->minimumPayoutAmount) {
            Craft::$app->getSession()->setError(
                Craft::t('kickback', 'Your balance ({balance}) is below the minimum payout amount ({minimum}).', [
                    'balance' => Craft::$app->getFormatter()->asCurrency($affiliate->pendingBalance, KickBack::getCommerceCurrency()),
                    'minimum' => Craft::$app->getFormatter()->asCurrency($settings->minimumPayoutAmount, KickBack::getCommerceCurrency()),
                ])
            );
            return $this->redirect('/' . $settings->getCurrentSitePortalPath() . '/settings');
        }

        $notes = Craft::$app->getRequest()->getBodyParam('notes');
        $payout = $plugin->payouts->createPayout($affiliate, $notes);

        if ($payout === null) {
            Craft::$app->getSession()->setError(
                Craft::t('kickback', 'portal.message.payoutRequestFailed')
            );
        } else {
            Craft::$app->getSession()->setNotice(
                Craft::t('kickback', 'Payout request submitted for {amount}.', [
                    'amount' => Craft::$app->getFormatter()->asCurrency($payout->amount, $payout->currency),
                ])
            );
        }

        return $this->redirect('/' . $settings->getCurrentSitePortalPath() . '/settings');
    }
}
