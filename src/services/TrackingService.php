<?php

declare(strict_types=1);

namespace anvildev\craftkickback\services;

use anvildev\craftkickback\elements\AffiliateElement;
use anvildev\craftkickback\helpers\DateHelper;
use anvildev\craftkickback\KickBack;
use anvildev\craftkickback\models\Referral;
use anvildev\craftkickback\models\Settings;
use anvildev\craftkickback\records\ClickRecord;
use anvildev\craftkickback\records\CouponRecord;
use Craft;
use craft\base\Component;

/**
 * Handles click tracking, referral cookie management, and affiliate attribution resolution.
 */
class TrackingService extends Component
{
    public function recordClick(AffiliateElement $affiliate, string $landingUrl): int
    {
        $request = Craft::$app->getRequest();
        $plugin = KickBack::getInstance();
        $settings = $plugin->getSettings();
        $ip = $request->getUserIP() ?? '0.0.0.0';

        $click = new ClickRecord();
        $click->affiliateId = $affiliate->id;
        $click->programId = $affiliate->programId;
        $click->ip = $ip;
        $click->userAgent = $request->getUserAgent();
        $click->referrerUrl = $request->getReferrer();
        $click->landingUrl = $landingUrl;
        $click->subId = $request->getQueryParam('sub_id');
        $click->isUnique = $this->isUniqueClick($affiliate->id, $ip);
        $click->dateCreated = DateHelper::nowString();
        $click->save(false);

        if ($settings->attributionModel === Settings::ATTRIBUTION_MODEL_FIRST_CLICK && $this->getReferralCookie() !== null) {
            return $click->id;
        }

        $program = $plugin->programs->getProgramById($affiliate->programId);
        $this->setReferralCookie(
            $affiliate->referralCode, $click->id,
            ($program !== null && $program->cookieDuration > 0) ? $program->cookieDuration : $settings->cookieDuration,
        );

        return $click->id;
    }

    /**
     * Set the HMAC-signed referral cookie.
     *
     * No-op outside a web request - yii\console\Response has no getCookies(),
     * so queue jobs and CLI-driven order processing must not crash here.
     */
    public function setReferralCookie(string $referralCode, int $clickId, int $durationDays): void
    {
        $response = Craft::$app->getResponse();
        if (!$response instanceof \yii\web\Response) {
            return;
        }

        $settings = KickBack::getInstance()->getSettings();
        $cookieName = $settings->cookieName;

        $json = json_encode([
            'code' => $referralCode,
            'clickId' => $clickId,
            'timestamp' => time(),
        ], JSON_THROW_ON_ERROR);

        $value = Craft::$app->getSecurity()->hashData($json);

        $cookie = new \yii\web\Cookie([
            'name' => $cookieName,
            'value' => $value,
            'expire' => time() + ($durationDays * 86400),
            'httpOnly' => true,
            'secure' => Craft::$app->getRequest()->getIsSecureConnection(),
            'sameSite' => \yii\web\Cookie::SAME_SITE_LAX,
        ]);

        $response->getCookies()->add($cookie);
    }

    /**
     * Read and HMAC-validate the referral cookie. Returns null on missing,
     * tampered, or malformed data (legacy unsigned cookies count as absent).
     *
     * Returns null outside a web request - yii\console\Request has no
     * getCookies(), so non-web callers cannot have a referral cookie.
     *
     * @return array{code: string, clickId: int, timestamp: int}|null
     */
    public function getReferralCookie(): ?array
    {
        $request = Craft::$app->getRequest();
        if (!$request instanceof \craft\web\Request) {
            return null;
        }

        $cookieName = KickBack::getInstance()->getSettings()->cookieName;
        $value = $request->getCookies()->getValue($cookieName);
        if ($value === null) {
            return null;
        }

        $json = Craft::$app->getSecurity()->validateData($value);
        if ($json === false) {
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['code'], $data['clickId'])) {
            return null;
        }

        return $data;
    }

    /**
     * No-op outside a web response. ReferralService::processAttribution()
     * calls this after order completion, which can happen in queue jobs.
     */
    public function clearReferralCookie(): void
    {
        $response = Craft::$app->getResponse();
        if (!$response instanceof \yii\web\Response) {
            return;
        }

        $response->getCookies()->remove(
            new \yii\web\Cookie(['name' => KickBack::getInstance()->getSettings()->cookieName]),
        );
    }

    /**
     * @return array{affiliate: AffiliateElement, clickId: int|null, method: string}|null
     */
    public function resolveAffiliate(): ?array
    {
        $cookie = $this->getReferralCookie();
        if ($cookie === null) {
            return null;
        }

        $affiliate = KickBack::getInstance()->affiliates->getAffiliateByReferralCode($cookie['code']);
        return $affiliate !== null && $affiliate->affiliateStatus === AffiliateElement::STATUS_ACTIVE
            ? ['affiliate' => $affiliate, 'clickId' => $cookie['clickId'], 'method' => Referral::ATTRIBUTION_COOKIE]
            : null;
    }

    /**
     * Distinct affiliates who clicked from the current IP within the cookie
     * window, newest click per affiliate. Used for linear attribution.
     *
     * @return array<array{affiliate: AffiliateElement, clickId: int, method: string}>
     */
    public function resolveAllAffiliates(): array
    {
        $ip = Craft::$app->getRequest()->getUserIP();
        if ($ip === null) {
            return [];
        }

        $settings = KickBack::getInstance()->getSettings();
        $cutoff = (new \DateTime())
            ->modify("-{$settings->cookieDuration} days")
            ->format('Y-m-d H:i:s');

        /** @var ClickRecord[] $clicks */
        $clicks = ClickRecord::find()
            ->where(['ip' => $ip])
            ->andWhere(['>=', 'dateCreated', $cutoff])
            ->orderBy(['dateCreated' => SORT_DESC])
            ->all();

        $seen = [];
        $results = [];

        foreach ($clicks as $click) {
            if (isset($seen[$click->affiliateId])) {
                continue;
            }
            $seen[$click->affiliateId] = true;

            $affiliate = KickBack::getInstance()->affiliates->getAffiliateById($click->affiliateId);
            if ($affiliate !== null && $affiliate->affiliateStatus === AffiliateElement::STATUS_ACTIVE) {
                $results[] = [
                    'affiliate' => $affiliate,
                    'clickId' => $click->id,
                    'method' => Referral::ATTRIBUTION_COOKIE,
                ];
            }
        }

        return $results;
    }

    public function resolveAffiliateFromCoupon(string $couponCode): ?AffiliateElement
    {
        $plugin = KickBack::getInstance();
        if (!$plugin->getSettings()->enableCouponTracking) {
            return null;
        }

        $coupon = CouponRecord::findOne(['code' => $couponCode]);
        $affiliate = $coupon !== null ? $plugin->affiliates->getAffiliateById($coupon->affiliateId) : null;
        return $affiliate?->affiliateStatus === AffiliateElement::STATUS_ACTIVE ? $affiliate : null;
    }

    /**
     * True when no prior click exists for the same affiliate + IP in the last 24h.
     */
    private function isUniqueClick(int $affiliateId, string $ip): bool
    {
        return !ClickRecord::find()
            ->where(['affiliateId' => $affiliateId, 'ip' => $ip])
            ->andWhere(['>=', 'dateCreated', DateHelper::pastCutoffString('-24 hours')])
            ->exists();
    }
}
