<?php

declare(strict_types=1);

namespace anvildev\craftkickback\services;

use anvildev\craftkickback\events\FraudEvent;
use anvildev\craftkickback\helpers\DateHelper;
use anvildev\craftkickback\KickBack;
use anvildev\craftkickback\models\Commission;
use anvildev\craftkickback\models\Referral;
use anvildev\craftkickback\records\ClickRecord;
use anvildev\craftkickback\records\ReferralRecord;
use Craft;
use craft\base\Component;
use craft\helpers\Json;

/**
 * Detects and manages fraudulent referral activity such as click velocity abuse and bot traffic.
 */
class FraudService extends Component
{
    public const EVENT_AFTER_FLAG_REFERRAL = 'afterFlagReferral';
    public const EVENT_AFTER_APPROVE_FLAGGED = 'afterApproveFlagged';
    public const EVENT_AFTER_REJECT_FLAGGED = 'afterRejectFlagged';

    private const BOT_PATTERNS = [
        'bot',
        'crawler',
        'spider',
        'scraper',
        'headless',
        'phantom',
        'selenium',
        'puppeteer',
        'wget',
        'curl/',
        'python-requests',
        'go-http-client',
        'java/',
        'libwww',
    ];

    /**
     * Evaluate a referral and return fraud flags (empty = clean).
     *
     * @return string[]
     */
    public function evaluateReferral(ReferralRecord $referral): array
    {
        if (!KickBack::getInstance()->getSettings()->enableFraudDetection) {
            return [];
        }

        $click = $referral->clickId !== null ? ClickRecord::findOne($referral->clickId) : null;

        $flags = array_values(array_filter([
            $click?->id !== null ? $this->checkClickVelocity($click) : null,
            $click?->id !== null ? $this->checkSuspiciousUserAgent($click) : null,
            $this->checkRapidConversions($referral),
            $this->checkDuplicateCustomer($referral),
            $click?->id !== null ? $this->checkIpReuse($click) : null,
        ]));

        Craft::info("Fraud evaluation referral #{$referral->id}: " . ($flags
            ? count($flags) . ' flag(s): ' . implode('; ', $flags)
            : 'clean'), __METHOD__);

        return $flags;
    }

    /**
     * Mark the referral as flagged and store the fraud flag list.
     *
     * @param string[] $flags
     */
    public function flagReferral(ReferralRecord $referral, array $flags): bool
    {
        $element = \anvildev\craftkickback\elements\ReferralElement::find()->id($referral->id)->one();
        if ($element === null) {
            return false;
        }

        $element->referralStatus = \anvildev\craftkickback\elements\ReferralElement::STATUS_FLAGGED;
        $element->fraudFlags = Json::encode($flags);

        if (!Craft::$app->getElements()->saveElement($element, false)) {
            return false;
        }
        $referral->refresh();

        Craft::warning("Referral #{$referral->id} flagged for fraud: " . implode(', ', $flags), __METHOD__);
        $this->trigger(self::EVENT_AFTER_FLAG_REFERRAL, new FraudEvent([
            'referral' => $referral,
            'fraudFlags' => $flags,
        ]));

        return true;
    }

    public function approveFlaggedReferral(ReferralRecord $referral): bool
    {
        return $this->transitionFlaggedReferral(
            $referral,
            \anvildev\craftkickback\elements\ReferralElement::STATUS_APPROVED,
            'approveCommission',
            self::EVENT_AFTER_APPROVE_FLAGGED,
            'approved after review',
        );
    }

    public function rejectFlaggedReferral(ReferralRecord $referral): bool
    {
        return $this->transitionFlaggedReferral(
            $referral,
            \anvildev\craftkickback\elements\ReferralElement::STATUS_REJECTED,
            'rejectCommission',
            self::EVENT_AFTER_REJECT_FLAGGED,
            'rejected',
        );
    }

    /**
     * Shared flagged-referral transition: flip the element status and
     * propagate it to pending commissions inside a single transaction.
     */
    private function transitionFlaggedReferral(
        ReferralRecord $referral,
        string $targetStatus,
        string $commissionMethod,
        string $afterEvent,
        string $logVerb,
    ): bool {
        if ($referral->status !== Referral::STATUS_FLAGGED) {
            return false;
        }

        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            $element = \anvildev\craftkickback\elements\ReferralElement::find()->id($referral->id)->one();
            if ($element === null) {
                $transaction->rollBack();
                return false;
            }

            $element->referralStatus = $targetStatus;
            if (!Craft::$app->getElements()->saveElement($element, false)) {
                $transaction->rollBack();
                return false;
            }
            $referral->refresh();

            $commissions = KickBack::getInstance()->commissions;
            foreach ($commissions->getCommissionsByReferralId($referral->id) as $commission) {
                if ($commission->status === Commission::STATUS_PENDING) {
                    $commissions->$commissionMethod($commission);
                }
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        Craft::info("Flagged referral #{$referral->id} {$logVerb}", __METHOD__);
        $this->trigger($afterEvent, new FraudEvent(['referral' => $referral]));

        return true;
    }

    /**
     * @return ReferralRecord[]
     */
    public function getFlaggedReferrals(): array
    {
        /** @var ReferralRecord[] */
        return ReferralRecord::find()
            ->where(['status' => Referral::STATUS_FLAGGED])
            ->orderBy(['dateCreated' => SORT_DESC])
            ->all();
    }

    /**
     * @return array{flagged: int, totalBlocked: int, recentFlags: ReferralRecord[]}
     */
    public function getFraudStats(): array
    {
        $flagged = fn() => ReferralRecord::find()->where(['status' => Referral::STATUS_FLAGGED]);
        /** @var ReferralRecord[] $recentFlags */
        $recentFlags = $flagged()->orderBy(['dateCreated' => SORT_DESC])->limit(5)->all();
        return [
            'flagged' => (int)$flagged()->count(),
            'totalBlocked' => (int)ReferralRecord::find()
                ->where(['status' => Referral::STATUS_REJECTED])
                ->andWhere(['is not', 'fraudFlags', null])
                ->count(),
            'recentFlags' => $recentFlags,
        ];
    }

    private function checkClickVelocity(ClickRecord $click): ?string
    {
        $settings = KickBack::getInstance()->getSettings();
        $threshold = $settings->fraudClickVelocityThreshold;
        $window = $settings->fraudClickVelocityWindow;

        $cutoff = (new \DateTime())->modify("-{$window} minutes")->format('Y-m-d H:i:s');
        $clickCount = (int)ClickRecord::find()
            ->where(['ip' => $click->ip])
            ->andWhere(['>=', 'dateCreated', $cutoff])
            ->count();

        Craft::info(
            "Fraud check (clickVelocity) click #{$click->id}: {$clickCount} clicks from {$click->ip} in {$window}min (threshold {$threshold})",
            __METHOD__,
        );

        if ($clickCount >= $threshold) {
            Craft::warning(
                "Fraud FLAG (clickVelocity) click #{$click->id}: {$clickCount} >= {$threshold} from {$click->ip}",
                __METHOD__,
            );
            return "click_velocity:{$clickCount}_clicks_from_{$click->ip}";
        }

        return null;
    }

    private function checkSuspiciousUserAgent(ClickRecord $click): ?string
    {
        if ($click->userAgent === null) {
            return null;
        }

        $ua = strtolower($click->userAgent);
        $frag = substr($click->userAgent, 0, 60);
        Craft::info("Fraud check (suspiciousUserAgent) click #{$click->id}: UA fragment \"{$frag}\"", __METHOD__);

        foreach (self::BOT_PATTERNS as $pattern) {
            if (str_contains($ua, $pattern)) {
                Craft::warning("Fraud FLAG (suspiciousUserAgent) click #{$click->id}: matched \"{$pattern}\" in \"{$frag}\"", __METHOD__);
                return "suspicious_user_agent:{$pattern}";
            }
        }

        if (strlen($click->userAgent) < 10) {
            Craft::warning("Fraud FLAG (suspiciousUserAgent) click #{$click->id}: UA too short ({$click->userAgent})", __METHOD__);
            return 'suspicious_user_agent:too_short';
        }

        return null;
    }

    private function checkRapidConversions(ReferralRecord $referral): ?string
    {
        $settings = KickBack::getInstance()->getSettings();
        $minutes = $settings->fraudRapidConversionMinutes;

        if ($minutes <= 0) {
            return null;
        }

        $cutoff = (new \DateTime())
            ->modify("-{$minutes} minutes")
            ->format('Y-m-d H:i:s');

        $recentCount = (int)ReferralRecord::find()
            ->where(['affiliateId' => $referral->affiliateId])
            ->andWhere(['>=', 'dateCreated', $cutoff])
            ->andWhere(['!=', 'id', $referral->id])
            ->count();

        Craft::info(
            "Fraud check (rapidConversions) referral #{$referral->id}: {$recentCount} other conversions for affiliate #{$referral->affiliateId} in {$minutes}min window",
            __METHOD__,
        );

        if ($recentCount > 0) {
            Craft::warning(
                "Fraud FLAG (rapidConversions) referral #{$referral->id}: {$recentCount} conversion(s) for affiliate #{$referral->affiliateId} within {$minutes}min",
                __METHOD__,
            );
            return "rapid_conversion:{$recentCount}_in_{$minutes}min";
        }

        return null;
    }

    private function checkDuplicateCustomer(ReferralRecord $referral): ?string
    {
        if (empty($referral->customerEmail)) {
            return null;
        }

        Craft::info(
            "Fraud check (duplicateCustomer) referral #{$referral->id}: checking for prior conversion by same customer for affiliate #{$referral->affiliateId}",
            __METHOD__,
        );

        $exists = ReferralRecord::find()
            ->where([
                'affiliateId' => $referral->affiliateId,
                'customerEmail' => $referral->customerEmail,
            ])
            ->andWhere(['!=', 'id', $referral->id])
            ->exists();

        if ($exists) {
            Craft::warning(
                "Fraud FLAG (duplicateCustomer) referral #{$referral->id}: customer has a prior conversion for affiliate #{$referral->affiliateId}",
                __METHOD__,
            );
            return "duplicate_customer:{$referral->customerEmail}";
        }

        return null;
    }

    private function checkIpReuse(ClickRecord $click): ?string
    {
        $cutoff = DateHelper::pastCutoffString('-24 hours');

        $affiliateCount = (int)ClickRecord::find()
            ->where(['ip' => $click->ip])
            ->andWhere(['>=', 'dateCreated', $cutoff])
            ->select('affiliateId')
            ->distinct()
            ->count();

        $threshold = KickBack::getInstance()->getSettings()->fraudIpReuseThreshold;

        Craft::info(
            "Fraud check (ipReuse) click #{$click->id}: {$affiliateCount} distinct affiliates from {$click->ip} in 24h (threshold {$threshold})",
            __METHOD__,
        );

        if ($affiliateCount >= $threshold) {
            Craft::warning(
                "Fraud FLAG (ipReuse) click #{$click->id}: {$affiliateCount} >= {$threshold} distinct affiliates from {$click->ip}",
                __METHOD__,
            );
            return "ip_reuse:{$affiliateCount}_affiliates_from_{$click->ip}";
        }

        return null;
    }
}
