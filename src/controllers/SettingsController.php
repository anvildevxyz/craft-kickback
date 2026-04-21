<?php

declare(strict_types=1);

namespace anvildev\craftkickback\controllers;

use anvildev\craftkickback\KickBack;
use Craft;
use craft\web\Controller;
use yii\web\Response;

/**
 * Plugin settings: commissions, tracking, fraud, payouts, gateways.
 */
class SettingsController extends Controller
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission(KickBack::PERMISSION_MANAGE_SETTINGS);

        return true;
    }

    public function actionIndex(): Response
    {
        return $this->renderTemplate('kickback/settings/index', [
            'settings' => KickBack::getInstance()->getSettings(),
            'currentSite' => Craft::$app->getSites()->getCurrentSite(),
        ]);
    }

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $plugin = KickBack::getInstance();
        $settings = $plugin->getSettings();

        $settings->defaultCommissionType = $request->getBodyParam('defaultCommissionType', $settings->defaultCommissionType);
        $settings->defaultCommissionRate = (float)$request->getBodyParam('defaultCommissionRate', $settings->defaultCommissionRate);

        $settings->cookieDuration = (int)$request->getBodyParam('cookieDuration', $settings->cookieDuration);
        $settings->cookieName = $request->getBodyParam('cookieName', $settings->cookieName);
        $settings->attributionModel = $request->getBodyParam('attributionModel', $settings->attributionModel);
        $settings->referralParamName = $request->getBodyParam('referralParamName', $settings->referralParamName);
        $settings->clickRetentionDays = (int)$request->getBodyParam('clickRetentionDays', $settings->clickRetentionDays);
        $settings->enableCouponTracking = (bool)$request->getBodyParam('enableCouponTracking', $settings->enableCouponTracking);
        $settings->enableCouponCreation = (bool)$request->getBodyParam('enableCouponCreation', $settings->enableCouponCreation);
        $settings->allowAffiliateSelfServiceCoupons = (bool)$request->getBodyParam('allowAffiliateSelfServiceCoupons', $settings->allowAffiliateSelfServiceCoupons);
        $settings->maxCouponsPerAffiliate = (int)$request->getBodyParam('maxCouponsPerAffiliate', $settings->maxCouponsPerAffiliate);
        $settings->maxSelfServiceDiscountPercent = (float)$request->getBodyParam('maxSelfServiceDiscountPercent', $settings->maxSelfServiceDiscountPercent);
        $settings->enableLifetimeCommissions = (bool)$request->getBodyParam('enableLifetimeCommissions', $settings->enableLifetimeCommissions);

        $settings->autoApproveAffiliates = (bool)$request->getBodyParam('autoApproveAffiliates', $settings->autoApproveAffiliates);
        $settings->autoApproveReferrals = (bool)$request->getBodyParam('autoApproveReferrals', $settings->autoApproveReferrals);
        $settings->holdPeriodDays = (int)$request->getBodyParam('holdPeriodDays', $settings->holdPeriodDays);

        $settings->minimumPayoutAmount = (float)$request->getBodyParam('minimumPayoutAmount', $settings->minimumPayoutAmount);

        $settings->requirePayoutVerification = (bool)$request->getBodyParam('requirePayoutVerification', $settings->requirePayoutVerification);
        $settings->notifyVerifierOnRequest = (bool)$request->getBodyParam('notifyVerifierOnRequest', $settings->notifyVerifierOnRequest);

        // elementSelectField posts an array with a single id; unwrap it.
        $verifierRaw = $request->getBodyParam('defaultPayoutVerifierId');
        if (is_array($verifierRaw)) {
            $verifierRaw = $verifierRaw[0] ?? null;
        }
        $settings->defaultPayoutVerifierId = $verifierRaw !== null && $verifierRaw !== '' ? (int)$verifierRaw : null;

        $settings->enableFraudDetection = (bool)$request->getBodyParam('enableFraudDetection', $settings->enableFraudDetection);
        $settings->fraudAutoFlag = (bool)$request->getBodyParam('fraudAutoFlag', $settings->fraudAutoFlag);
        $settings->fraudClickVelocityThreshold = (int)$request->getBodyParam('fraudClickVelocityThreshold', $settings->fraudClickVelocityThreshold);
        $settings->fraudClickVelocityWindow = (int)$request->getBodyParam('fraudClickVelocityWindow', $settings->fraudClickVelocityWindow);
        $settings->fraudRapidConversionMinutes = (int)$request->getBodyParam('fraudRapidConversionMinutes', $settings->fraudRapidConversionMinutes);
        $settings->fraudIpReuseThreshold = (int)$request->getBodyParam('fraudIpReuseThreshold', $settings->fraudIpReuseThreshold);

        $settings->enableMultiTier = (bool)$request->getBodyParam('enableMultiTier', $settings->enableMultiTier);
        $settings->maxMlmDepth = (int)$request->getBodyParam('maxMlmDepth', $settings->maxMlmDepth);
        $settings->excludeShippingFromCommission = (bool)$request->getBodyParam('excludeShippingFromCommission', $settings->excludeShippingFromCommission);
        $settings->excludeTaxFromCommission = (bool)$request->getBodyParam('excludeTaxFromCommission', $settings->excludeTaxFromCommission);
        $settings->reverseCommissionOnRefund = (bool)$request->getBodyParam('reverseCommissionOnRefund', $settings->reverseCommissionOnRefund);

        $cancelledRaw = $request->getBodyParam('cancelledStatusHandles', '');
        $settings->cancelledStatusHandles = array_values(array_filter(
            array_map('trim', explode(',', (string)$cancelledRaw)),
            fn(string $s) => $s !== '',
        ));

        // Portal settings are posted per-site; merge into existing arrays so
        // saving from one site doesn't wipe others.
        $currentSiteHandle = Craft::$app->getSites()->getCurrentSite()->handle;

        $paths = $settings->affiliatePortalPaths;
        $postedPath = trim((string)$request->getBodyParam('portalPath', ''));
        if ($postedPath !== '') {
            $paths[$currentSiteHandle] = $postedPath;
        } else {
            unset($paths[$currentSiteHandle]);
        }
        $settings->affiliatePortalPaths = $paths;

        $enabled = $settings->affiliatePortalEnabledSites;
        if ((bool)$request->getBodyParam('portalEnabled')) {
            $enabled[$currentSiteHandle] = true;
        } else {
            unset($enabled[$currentSiteHandle]);
        }
        $settings->affiliatePortalEnabledSites = $enabled;

        $settings->paypalClientId = $request->getBodyParam('paypalClientId', $settings->paypalClientId);
        $settings->paypalClientSecret = $request->getBodyParam('paypalClientSecret', $settings->paypalClientSecret);
        $settings->paypalSandbox = (bool)$request->getBodyParam('paypalSandbox', $settings->paypalSandbox);
        $settings->paypalWebhookId = $request->getBodyParam('paypalWebhookId', $settings->paypalWebhookId);
        $settings->stripeSecretKey = $request->getBodyParam('stripeSecretKey', $settings->stripeSecretKey);
        $settings->stripeWebhookSecret = $request->getBodyParam('stripeWebhookSecret', $settings->stripeWebhookSecret);
        $settings->batchAutoProcessEnabled = (bool)$request->getBodyParam('batchAutoProcessEnabled', $settings->batchAutoProcessEnabled);
        $settings->batchAutoProcessCadence = $request->getBodyParam('batchAutoProcessCadence', $settings->batchAutoProcessCadence);

        if (!Craft::$app->getPlugins()->savePluginSettings($plugin, $settings->toArray())) {
            Craft::$app->getSession()->setError(Craft::t('kickback', 'settings.message.saveFailed'));

            Craft::$app->getUrlManager()->setRouteParams([
                'settings' => $settings,
            ]);

            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('kickback', 'settings.message.saved'));

        return $this->redirectToPostedUrl();
    }
}
