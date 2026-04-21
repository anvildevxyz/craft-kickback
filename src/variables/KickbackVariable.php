<?php

declare(strict_types=1);

namespace anvildev\craftkickback\variables;

use anvildev\craftkickback\elements\AffiliateElement;
use anvildev\craftkickback\elements\db\AffiliateQuery;
use anvildev\craftkickback\KickBack;
use anvildev\craftkickback\records\ReferralRecord;
use Craft;

class KickbackVariable
{
    private bool $_affiliateLoaded = false;
    private ?AffiliateElement $_affiliate = null;

    public function currentAffiliate(): ?AffiliateElement
    {
        if ($this->_affiliateLoaded) {
            return $this->_affiliate;
        }

        $this->_affiliateLoaded = true;

        $user = Craft::$app->getUser()->getIdentity();
        if ($user === null) {
            return null;
        }

        $this->_affiliate = KickBack::getInstance()->affiliates->getAffiliateByUserId($user->id);

        return $this->_affiliate;
    }

    public function referralLink(?string $url = null): ?string
    {
        return $this->currentAffiliate()?->getReferralUrl($url);
    }

    public function referralCode(): ?string
    {
        return $this->currentAffiliate()?->referralCode;
    }

    public function activeReferralCode(): ?string
    {
        $cookie = KickBack::getInstance()->tracking->getReferralCookie();
        return is_array($cookie) ? $cookie['code'] : null;
    }

    /**
     * Resolve the used referral code for a completed order.
     * Prefers couponCode, then falls back to affiliate referralCode.
     */
    public function referralCodeForOrder(?int $orderId): ?string
    {
        if ($orderId === null) {
            return null;
        }

        /** @var ReferralRecord|null $referral */
        $referral = ReferralRecord::find()->where(['orderId' => $orderId])->orderBy(['id' => SORT_DESC])->one();
        if ($referral === null) {
            return null;
        }

        return !empty($referral->couponCode)
            ? $referral->couponCode
            : ($referral->affiliateId !== null
                ? KickBack::getInstance()->affiliates->getAffiliateById((int)$referral->affiliateId)?->referralCode
                : null);
    }

    public function balance(): float
    {
        return $this->currentAffiliate()?->pendingBalance ?? 0.0;
    }

    /**
     * @return array<string, mixed>
     */
    public function earnings(): array
    {
        $affiliate = $this->currentAffiliate();
        if ($affiliate === null) {
            return ['lifetime' => 0.0, 'pending' => 0.0, 'referrals' => 0];
        }

        return [
            'lifetime' => $affiliate->lifetimeEarnings,
            'pending' => $affiliate->pendingBalance,
            'referrals' => $affiliate->lifetimeReferrals,
        ];
    }

    public function affiliates(): AffiliateQuery
    {
        return AffiliateElement::find();
    }

    public function isAffiliate(): bool
    {
        $user = Craft::$app->getUser()->getIdentity();
        return $user !== null && KickBack::getInstance()->affiliates->isAffiliate($user->id);
    }

    public function isReferred(): bool
    {
        $cookieName = KickBack::getInstance()->getSettings()->cookieName;

        return Craft::$app->getRequest()->getCookies()->has($cookieName);
    }

    public function currency(): string
    {
        return KickBack::getCommerceCurrency();
    }

    public function settings(): \anvildev\craftkickback\models\Settings
    {
        return KickBack::getInstance()->getSettings();
    }
}
