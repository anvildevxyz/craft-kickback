<?php

declare(strict_types=1);

namespace anvildev\craftkickback\services;

use anvildev\craftkickback\elements\AffiliateElement;
use anvildev\craftkickback\events\AffiliateEvent;
use anvildev\craftkickback\helpers\UniqueCodeHelper;
use anvildev\craftkickback\KickBack;
use Craft;
use craft\base\Component;
use craft\elements\User;
use craft\helpers\DateTimeHelper;
use craft\helpers\StringHelper;

/**
 * Handles affiliate registration, status transitions, and balance management.
 */
class AffiliateService extends Component
{
    public const EVENT_BEFORE_APPROVE_AFFILIATE = 'beforeApproveAffiliate';
    public const EVENT_AFTER_APPROVE_AFFILIATE = 'afterApproveAffiliate';
    public const EVENT_BEFORE_REJECT_AFFILIATE = 'beforeRejectAffiliate';
    public const EVENT_AFTER_REJECT_AFFILIATE = 'afterRejectAffiliate';
    public const EVENT_BEFORE_SUSPEND_AFFILIATE = 'beforeSuspendAffiliate';
    public const EVENT_AFTER_SUSPEND_AFFILIATE = 'afterSuspendAffiliate';
    public const EVENT_BEFORE_REACTIVATE_AFFILIATE = 'beforeReactivateAffiliate';
    public const EVENT_AFTER_REACTIVATE_AFFILIATE = 'afterReactivateAffiliate';

    public function getAffiliateById(int $id): ?AffiliateElement
    {
        return AffiliateElement::find()->id($id)->one();
    }

    public function getAffiliateByReferralCode(string $code): ?AffiliateElement
    {
        return AffiliateElement::find()->referralCode($code)->one();
    }

    /**
     * @param int[] $ids
     * @return array<int, AffiliateElement>
     */
    public function getAffiliatesByIds(array $ids): array
    {
        return ($ids = array_unique(array_filter($ids)))
            ? array_column(AffiliateElement::find()->id($ids)->all(), null, 'id')
            : [];
    }

    public function getAffiliateByUserId(int $userId): ?AffiliateElement
    {
        return AffiliateElement::find()->userId($userId)->one();
    }

    /**
     * @return AffiliateElement[]
     */
    public function getAffiliatesByParentId(int $parentId): array
    {
        return AffiliateElement::find()
            ->where(['parentAffiliateId' => $parentId])
            ->orderBy(['dateCreated' => SORT_DESC])
            ->all();
    }

    /**
     * Register a new affiliate from a Craft user.
     *
     * @param array<string, mixed> $attributes
     */
    public function registerAffiliate(User $user, int $programId, array $attributes = []): ?AffiliateElement
    {
        $affiliate = new AffiliateElement();
        $affiliate->userId = $user->id;
        $affiliate->programId = $programId;
        $affiliate->title = $user->fullName ?: $user->username ?: $user->email;
        $affiliate->referralCode = $attributes['referralCode'] ?? $this->generateReferralCode($user);

        foreach (['parentAffiliateId', 'groupId', 'paypalEmail', 'payoutMethod', 'notes'] as $key) {
            if (isset($attributes[$key])) {
                $affiliate->$key = in_array($key, ['parentAffiliateId', 'groupId'], true)
                    ? (int)$attributes[$key]
                    : $attributes[$key];
            }
        }

        if (KickBack::getInstance()->getSettings()->autoApproveAffiliates) {
            $affiliate->affiliateStatus = AffiliateElement::STATUS_ACTIVE;
            $affiliate->dateApproved = DateTimeHelper::currentUTCDateTime();
        } else {
            $affiliate->affiliateStatus = AffiliateElement::STATUS_PENDING;
        }

        if (!Craft::$app->getElements()->saveElement($affiliate)) {
            Craft::error('Failed to save affiliate: ' . implode(', ', $affiliate->getErrorSummary(true)), __METHOD__);
            return null;
        }

        return $affiliate;
    }

    public function approveAffiliate(AffiliateElement $affiliate): bool
    {
        return $this->transitionStatus(
            $affiliate,
            AffiliateElement::STATUS_ACTIVE,
            'approved',
            self::EVENT_BEFORE_APPROVE_AFFILIATE,
            self::EVENT_AFTER_APPROVE_AFFILIATE,
            static fn(AffiliateElement $a) => $a->dateApproved = DateTimeHelper::currentUTCDateTime(),
        );
    }

    public function rejectAffiliate(AffiliateElement $affiliate): bool
    {
        return $this->transitionStatus(
            $affiliate,
            AffiliateElement::STATUS_REJECTED,
            'rejected',
            self::EVENT_BEFORE_REJECT_AFFILIATE,
            self::EVENT_AFTER_REJECT_AFFILIATE,
        );
    }

    public function suspendAffiliate(AffiliateElement $affiliate): bool
    {
        return $this->transitionStatus(
            $affiliate,
            AffiliateElement::STATUS_SUSPENDED,
            'suspended',
            self::EVENT_BEFORE_SUSPEND_AFFILIATE,
            self::EVENT_AFTER_SUSPEND_AFFILIATE,
        );
    }

    public function reactivateAffiliate(AffiliateElement $affiliate): bool
    {
        return $this->transitionStatus(
            $affiliate,
            AffiliateElement::STATUS_ACTIVE,
            'reactivated',
            self::EVENT_BEFORE_REACTIVATE_AFFILIATE,
            self::EVENT_AFTER_REACTIVATE_AFFILIATE,
        );
    }

    private function transitionStatus(
        AffiliateElement $affiliate,
        string $newStatus,
        string $verb,
        string $beforeEvent,
        string $afterEvent,
        ?callable $extraMutation = null,
    ): bool {
        $event = new AffiliateEvent(['affiliate' => $affiliate]);
        $this->trigger($beforeEvent, $event);
        if (!$event->isValid) {
            return false;
        }

        $affiliate->affiliateStatus = $newStatus;
        if ($extraMutation !== null) {
            $extraMutation($affiliate);
        }

        if (!Craft::$app->getElements()->saveElement($affiliate)) {
            return false;
        }

        Craft::info("Affiliate #{$affiliate->id} {$verb}", __METHOD__);
        $this->trigger($afterEvent, new AffiliateEvent(['affiliate' => $affiliate]));
        return true;
    }

    public function addPendingBalance(AffiliateElement $affiliate, float $amount): bool
    {
        $affiliate->pendingBalance = KickBack::getInstance()->commissions->roundMoney($affiliate->pendingBalance + $amount);
        if ($result = Craft::$app->getElements()->saveElement($affiliate)) {
            Craft::info("Affiliate #{$affiliate->id} balance +{$amount} (now {$affiliate->pendingBalance})", __METHOD__);
        }
        return $result;
    }

    public function deductPendingBalance(AffiliateElement $affiliate, float $amount): bool
    {
        $affiliate->pendingBalance = KickBack::getInstance()->commissions->roundMoney($affiliate->pendingBalance - $amount);
        if ($result = Craft::$app->getElements()->saveElement($affiliate)) {
            Craft::info("Affiliate #{$affiliate->id} balance -{$amount} (now {$affiliate->pendingBalance})", __METHOD__);
        }
        return $result;
    }

    public function recordPayout(AffiliateElement $affiliate, float $amount): bool
    {
        $round = KickBack::getInstance()->commissions->roundMoney(...);
        $affiliate->pendingBalance = $round($affiliate->pendingBalance - $amount);
        $affiliate->lifetimeEarnings = $round($affiliate->lifetimeEarnings + $amount);
        if ($result = Craft::$app->getElements()->saveElement($affiliate)) {
            Craft::info("Affiliate #{$affiliate->id} payout recorded: {$amount} (balance: {$affiliate->pendingBalance}, lifetime: {$affiliate->lifetimeEarnings})", __METHOD__);
        }
        return $result;
    }

    public function incrementReferralCount(AffiliateElement $affiliate): bool
    {
        $affiliate->lifetimeReferrals++;
        return Craft::$app->getElements()->saveElement($affiliate);
    }

    public function generateReferralCode(User $user): string
    {
        return UniqueCodeHelper::generate(
            StringHelper::slugify($user->username ?: $user->fullName ?: $user->email),
            fn(string $code) => AffiliateElement::find()->referralCode($code)->exists(),
        );
    }

    public function isAffiliate(int $userId): bool
    {
        return AffiliateElement::find()->userId($userId)->exists();
    }
}
