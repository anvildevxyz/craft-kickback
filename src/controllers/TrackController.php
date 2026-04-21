<?php

declare(strict_types=1);

namespace anvildev\craftkickback\controllers;

use anvildev\craftkickback\elements\AffiliateElement;
use anvildev\craftkickback\KickBack;
use Craft;
use craft\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Referral link click tracking, cookie setting, and same-site redirect.
 */
class TrackController extends Controller
{
    protected array|bool|int $allowAnonymous = ['track'];

    public function actionTrack(string $code): Response
    {
        $affiliate = KickBack::getInstance()->affiliates->getAffiliateByReferralCode($code);
        if ($affiliate === null || $affiliate->affiliateStatus !== AffiliateElement::STATUS_ACTIVE) {
            throw new NotFoundHttpException('Invalid referral link.');
        }

        $siteBaseUrl = Craft::$app->getSites()->getCurrentSite()->getBaseUrl();
        $destinationUrl = Craft::$app->getRequest()->getQueryParam('url');

        if (empty($destinationUrl) || !$this->isAllowedRedirectUrl($destinationUrl)) {
            $destinationUrl = $siteBaseUrl;
        }

        KickBack::getInstance()->tracking->recordClick($affiliate, $destinationUrl);

        return $this->redirect($destinationUrl);
    }

    /**
     * Prevent open redirects: allow relative paths and any configured site host.
     */
    private function isAllowedRedirectUrl(string $url): bool
    {
        $parsed = parse_url($url);
        if (!isset($parsed['host'])) {
            return true;
        }

        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $siteHost = parse_url($site->getBaseUrl(), PHP_URL_HOST);
            if ($siteHost && strcasecmp($parsed['host'], $siteHost) === 0) {
                return true;
            }
        }

        return false;
    }
}
