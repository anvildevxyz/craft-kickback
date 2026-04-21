<?php

declare(strict_types=1);

namespace anvildev\craftkickback\services;

use anvildev\craftkickback\elements\AffiliateElement;
use anvildev\craftkickback\events\CommissionEvent;
use anvildev\craftkickback\KickBack;
use anvildev\craftkickback\models\Commission;
use anvildev\craftkickback\models\Referral;
use anvildev\craftkickback\records\CommissionRecord;
use anvildev\craftkickback\records\ReferralRecord;
use Craft;
use craft\base\Component;
use craft\commerce\elements\Order;
use craft\commerce\models\LineItem;
use craft\elements\Category;
use craft\helpers\DateTimeHelper;

/**
 * Handles commission creation, approval, rejection, reversal, and rate resolution.
 */
class CommissionService extends Component
{
    public const EVENT_BEFORE_CREATE_COMMISSION = 'beforeCreateCommission';
    public const EVENT_AFTER_CREATE_COMMISSION = 'afterCreateCommission';
    public const EVENT_BEFORE_APPROVE_COMMISSION = 'beforeApproveCommission';
    public const EVENT_AFTER_APPROVE_COMMISSION = 'afterApproveCommission';
    public const EVENT_BEFORE_REJECT_COMMISSION = 'beforeRejectCommission';
    public const EVENT_AFTER_REJECT_COMMISSION = 'afterRejectCommission';
    public const EVENT_BEFORE_REVERSE_COMMISSION = 'beforeReverseCommission';
    public const EVENT_AFTER_REVERSE_COMMISSION = 'afterReverseCommission';

    /**
     * Create a commission for a referral, resolving rates per line item.
     *
     * @param float $splitFactor For linear attribution, the fraction this
     *     affiliate should receive (1/N across N attributions).
     */
    public function createCommission(
        ReferralRecord $referral,
        AffiliateElement $affiliate,
        Order $order,
        ?string $currency = null,
        float $splitFactor = 1.0,
    ): ?CommissionRecord {
        $plugin = KickBack::getInstance();
        $currency = $currency ?? KickBack::getCommerceCurrency();
        $baseChain = $this->resolveBaseRate($affiliate);

        $totalAmount = 0.0;
        $rawSubtotal = 0.0;
        $ruleNames = [];
        $firstResolution = null;

        $lineResolutionTrace = [];

        foreach ($order->getLineItems() as $lineItem) {
            $lineSubtotal = (float)$lineItem->getSubtotal();
            $rawSubtotal += $lineSubtotal;

            $resolution = $this->resolveLineItemRate($affiliate, $lineItem, $baseChain);
            $firstResolution ??= $resolution;

            if ($lineSubtotal <= 0) {
                continue;
            }

            [$rate, $rateType, $ruleName] = $resolution;
            $totalAmount += $this->calculateAmount($lineSubtotal, $rate, $rateType);
            $ruleNames[$ruleName] = true;

            $lineResolutionTrace[] = [
                'lineSubtotal' => $lineSubtotal,
                'lineItemId' => $lineItem->id,
                'purchasableId' => $lineItem->getPurchasable()?->id,
                'resolvedRate' => $rate,
                'resolvedRateType' => $rateType,
                'resolvedRule' => $ruleName,
            ];
        }

        $totalAmount = $this->roundMoney($totalAmount * $splitFactor);
        if ($totalAmount <= 0) {
            return null;
        }

        $orderSubtotal = $this->roundMoney($rawSubtotal * $splitFactor);

        if (count($ruleNames) === 1) {
            [$rate, $rateType, $ruleApplied] = $firstResolution ?? $baseChain;
        } else {
            $rate = $orderSubtotal > 0
                ? round(($totalAmount / $orderSubtotal) * 100, 2)
                : 0.0;
            $rateType = Commission::RATE_TYPE_PERCENTAGE;
            $ruleApplied = 'per-item: ' . count($ruleNames) . ' rules';
        }

        $ruleResolutionTrace = [
            'baseChain' => [
                'rate' => $baseChain[0],
                'rateType' => $baseChain[1],
                'rule' => $baseChain[2],
            ],
            'lineItems' => $lineResolutionTrace,
            'distinctRules' => array_keys($ruleNames),
            'resolved' => [
                'rate' => $rate,
                'rateType' => $rateType,
                'ruleApplied' => $ruleApplied,
            ],
            'splitFactor' => $splitFactor,
            'rawSubtotal' => $rawSubtotal,
            'splitSubtotal' => $orderSubtotal,
            'commissionAmount' => $totalAmount,
        ];

        $commission = $this->saveCommission(
            $referral,
            $affiliate,
            $totalAmount,
            $rate,
            $rateType,
            $ruleApplied,
            1,
            $currency,
            $ruleResolutionTrace,
        );

        if ($plugin->getSettings()->enableMultiTier) {
            $this->createMultiTierCommissions($referral, $affiliate, $orderSubtotal, $currency);
        }

        return $commission;
    }

    /**
     * Resolve the cart-scoped fallback chain: affiliate override -> bonus ->
     * tiered -> group -> program -> global. Product/category rules are
     * applied per line by resolveLineItemRate and fall back here on miss.
     *
     * @return array{0: float, 1: string, 2: string}
     */
    private function resolveBaseRate(AffiliateElement $affiliate): array
    {
        if ($affiliate->commissionRateOverride !== null && $affiliate->commissionTypeOverride !== null) {
            return [$affiliate->commissionRateOverride, $affiliate->commissionTypeOverride, 'affiliate_override'];
        }

        $p = KickBack::getInstance();
        $r = $p->commissionRules;

        if ($rule = $r->findBonusRule($affiliate->programId)) {
            return [$rule->commissionRate, $rule->commissionType, "rule:bonus:{$rule->name}"];
        }
        if ($rule = $r->findTieredRule($affiliate->programId, $affiliate->id)) {
            return [$rule->commissionRate, $rule->commissionType, "rule:tiered:{$rule->name}"];
        }
        if ($affiliate->groupId !== null && ($g = $p->affiliateGroups->getGroupById($affiliate->groupId))) {
            return [$g->commissionRate, $g->commissionType, "group:{$g->handle}"];
        }
        if ($prog = $p->programs->getProgramById($affiliate->programId)) {
            return [$prog->defaultCommissionRate, $prog->defaultCommissionType, "program:{$prog->handle}"];
        }

        $s = $p->getSettings();
        return [$s->defaultCommissionRate, $s->defaultCommissionType, 'global_default'];
    }

    /**
     * Resolve the rate for a single line item. Product rules beat category
     * rules (highest-specificity wins); falls back to the base chain.
     *
     * @param array{0: float, 1: string, 2: string} $baseChain
     * @return array{0: float, 1: string, 2: string}
     */
    private function resolveLineItemRate(AffiliateElement $affiliate, LineItem $lineItem, array $baseChain): array
    {
        $purchasable = $lineItem->getPurchasable();
        if ($purchasable === null) {
            return $baseChain;
        }

        $rules = KickBack::getInstance()->commissionRules;
        $pid = $affiliate->programId;
        $purchasableId = (int)$purchasable->id;
        $parentId = isset($purchasable->primaryOwnerId) && $purchasable->primaryOwnerId !== null
            ? (int)$purchasable->primaryOwnerId : null;

        $rule = $rules->findProductRule($pid, $purchasableId)
            ?? ($parentId !== null ? $rules->findProductRule($pid, $parentId) : null);
        if ($rule !== null) {
            return [$rule->commissionRate, $rule->commissionType, "rule:product:{$rule->name}"];
        }

        $categoryIds = Category::find()->relatedTo($purchasableId)->ids();
        if ($parentId !== null) {
            $categoryIds = array_merge($categoryIds, Category::find()->relatedTo($parentId)->ids());
        }

        foreach (array_unique(array_map('intval', $categoryIds)) as $categoryId) {
            if ($rule = $rules->findCategoryRule($pid, $categoryId)) {
                return [$rule->commissionRate, $rule->commissionType, "rule:category:{$rule->name}"];
            }
        }

        return $baseChain;
    }

    /**
     * Create commissions for parent affiliates in the MLM chain. Tier rules
     * apply to the full order subtotal, not per-item.
     */
    public function createMultiTierCommissions(
        ReferralRecord $referral,
        AffiliateElement $affiliate,
        float $orderSubtotal,
        ?string $currency = null,
    ): void {
        $plugin = KickBack::getInstance();
        $maxDepth = $plugin->getSettings()->maxMlmDepth;
        $currency ??= KickBack::getCommerceCurrency();
        $cur = $affiliate;

        for ($tier = 2; $tier <= $maxDepth && $cur->parentAffiliateId !== null; $tier++) {
            $parent = $plugin->affiliates->getAffiliateById($cur->parentAffiliateId);
            if ($parent === null || $parent->affiliateStatus !== AffiliateElement::STATUS_ACTIVE) {
                break;
            }

            $rule = $plugin->commissionRules->findMlmTierRule($parent->programId, $tier);
            if ($rule !== null) {
                $amount = $this->calculateAmount($orderSubtotal, $rule->commissionRate, $rule->commissionType);
                $label = "mlm_tier:{$tier}";
                if ($amount > 0) {
                    $this->saveCommission($referral, $parent, $amount, $rule->commissionRate, $rule->commissionType, $label, $tier, $currency, [
                        'mode' => 'mlm_tier', 'tier' => $tier,
                        'resolved' => ['rate' => $rule->commissionRate, 'rateType' => $rule->commissionType, 'ruleApplied' => $label],
                        'sourceReferralSubtotal' => $orderSubtotal, 'commissionAmount' => $amount,
                    ]);
                }
            }
            $cur = $parent;
        }
    }

    public function approveCommission(CommissionRecord $commission): bool
    {
        return $this->lifecycleTransition(
            $commission,
            \anvildev\craftkickback\elements\CommissionElement::STATUS_APPROVED,
            self::EVENT_BEFORE_APPROVE_COMMISSION,
            self::EVENT_AFTER_APPROVE_COMMISSION,
            'approved (amount: %amount, affiliate: %affiliateId)',
            transactional: true,
            balanceDelta: +1,
            stampDateApproved: true,
        );
    }

    public function rejectCommission(CommissionRecord $commission): bool
    {
        return $this->lifecycleTransition(
            $commission,
            \anvildev\craftkickback\elements\CommissionElement::STATUS_REJECTED,
            self::EVENT_BEFORE_REJECT_COMMISSION,
            self::EVENT_AFTER_REJECT_COMMISSION,
            'rejected (affiliate: %affiliateId)',
        );
    }

    public function reverseCommission(CommissionRecord $commission): bool
    {
        return $this->lifecycleTransition(
            $commission,
            \anvildev\craftkickback\elements\CommissionElement::STATUS_REVERSED,
            self::EVENT_BEFORE_REVERSE_COMMISSION,
            self::EVENT_AFTER_REVERSE_COMMISSION,
            'reversed (amount: %amount, affiliate: %affiliateId)',
            transactional: true,
            balanceDelta: -1,
            balanceDeltaGateStatus: Commission::STATUS_APPROVED,
            stampDateReversed: true,
        );
    }

    /**
     * Shared approve/reject/reverse pipeline.
     *
     * @param int $balanceDelta +1 credits, -1 debits, 0 skips balance update.
     * @param string|null $balanceDeltaGateStatus only apply balance delta when
     *     the commission's status at entry equals this value.
     */
    private function lifecycleTransition(
        CommissionRecord $commission,
        string $targetStatus,
        string $beforeEvent,
        string $afterEvent,
        string $logTemplate,
        bool $transactional = false,
        int $balanceDelta = 0,
        ?string $balanceDeltaGateStatus = null,
        bool $stampDateApproved = false,
        bool $stampDateReversed = false,
    ): bool {
        $plugin = KickBack::getInstance();
        $affiliate = $plugin->affiliates->getAffiliateById($commission->affiliateId);

        $event = new CommissionEvent(['commission' => $commission, 'affiliate' => $affiliate]);
        $this->trigger($beforeEvent, $event);
        if (!$event->isValid) {
            return false;
        }

        $previousStatus = $commission->status;
        $transaction = $transactional ? Craft::$app->getDb()->beginTransaction() : null;
        try {
            $element = \anvildev\craftkickback\elements\CommissionElement::find()->id($commission->id)->one();
            if ($element === null) {
                $transaction?->rollBack();
                return false;
            }

            $element->commissionStatus = $targetStatus;
            if ($stampDateApproved) {
                $element->dateApproved = DateTimeHelper::currentUTCDateTime();
            }
            if ($stampDateReversed) {
                $element->dateReversed = DateTimeHelper::currentUTCDateTime();
            }

            if (!Craft::$app->getElements()->saveElement($element, false)) {
                $transaction?->rollBack();
                return false;
            }
            $commission->refresh();

            if ($balanceDelta !== 0 && $affiliate !== null
                && ($balanceDeltaGateStatus === null || $previousStatus === $balanceDeltaGateStatus)
            ) {
                $amount = (float)$commission->amount;
                if ($balanceDelta > 0) {
                    $plugin->affiliates->addPendingBalance($affiliate, $amount);
                } else {
                    $plugin->affiliates->deductPendingBalance($affiliate, $amount);
                }
            }

            $transaction?->commit();
        } catch (\Throwable $e) {
            $transaction?->rollBack();
            throw $e;
        }

        Craft::info("Commission #{$commission->id} " . strtr($logTemplate, [
            '%amount' => (string)$commission->amount,
            '%affiliateId' => (string)$commission->affiliateId,
        ]), __METHOD__);

        $this->trigger($afterEvent, new CommissionEvent([
            'commission' => $commission,
            'affiliate' => $affiliate,
        ]));

        return true;
    }

    /**
     * Idempotently reduce a set of commissions to reflect a CUMULATIVE refund
     * ratio. Running twice with the same ratio is a no-op; running twice with
     * increasing ratios produces the correct new state without compounding.
     * refundRatio >= 0.95 fully reverses the commission.
     *
     * @param CommissionRecord[] $commissions
     */
    public function reverseCommissionsProportionally(array $commissions, float $refundRatio): void
    {
        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            foreach ($commissions as $commission) {
                if (in_array($commission->status, [Commission::STATUS_REVERSED, Commission::STATUS_REJECTED], true)) {
                    continue;
                }

                if ($refundRatio >= 0.95) {
                    $this->reverseCommission($commission);
                    continue;
                }

                $element = \anvildev\craftkickback\elements\CommissionElement::find()->id($commission->id)->one();
                if ($element === null) {
                    continue;
                }

                // Recompute from the immutable originalAmount snapshot; fall
                // back to current amount for rows migrated in before the column
                // existed (not idempotent for those, but still correct on first run).
                $originalAmount = $element->originalAmount > 0
                    ? $element->originalAmount
                    : (float)$commission->amount;

                $newAmount = $this->roundMoney($originalAmount * (1 - $refundRatio));
                $previousAmount = (float)$commission->amount;
                $delta = $this->roundMoney($previousAmount - $newAmount);

                // Half-a-cent threshold handles non-zero-minor-unit currencies (JPY).
                if (abs($delta) < 0.005) {
                    continue;
                }

                $reverseEvent = new CommissionEvent(['commission' => $commission]);
                $this->trigger(self::EVENT_BEFORE_REVERSE_COMMISSION, $reverseEvent);
                if (!$reverseEvent->isValid) {
                    continue;
                }

                $element->amount = $newAmount;
                if (!Craft::$app->getElements()->saveElement($element, false)) {
                    continue;
                }
                $commission->refresh();

                Craft::info(
                    "Commission #{$commission->id} reversed by refund: {$previousAmount} → {$commission->amount}",
                    __METHOD__,
                );

                $this->trigger(self::EVENT_AFTER_REVERSE_COMMISSION, new CommissionEvent(['commission' => $commission]));

                if ($commission->status === Commission::STATUS_APPROVED) {
                    $affiliate = KickBack::getInstance()->affiliates->getAffiliateById($commission->affiliateId);
                    if ($affiliate !== null && $delta > 0) {
                        KickBack::getInstance()->affiliates->deductPendingBalance($affiliate, $delta);
                    }
                }
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * @return CommissionRecord[]
     */
    public function getCommissionsByReferralId(int $referralId): array
    {
        /** @var CommissionRecord[] */
        return CommissionRecord::find()->where(['referralId' => $referralId])->all();
    }

    /**
     * @return CommissionRecord[]
     */
    public function getCommissionsByAffiliateId(int $affiliateId, ?string $status = null, ?int $limit = null, ?int $offset = null): array
    {
        $query = $this->affiliateQuery($affiliateId, $status);
        $limit !== null && $query->limit($limit);
        $offset !== null && $query->offset($offset);
        /** @var CommissionRecord[] */
        return $query->orderBy(['dateCreated' => SORT_DESC])->all();
    }

    public function countCommissionsByAffiliateId(int $affiliateId, ?string $status = null): int
    {
        return (int)$this->affiliateQuery($affiliateId, $status)->count();
    }

    private function affiliateQuery(int $affiliateId, ?string $status): \yii\db\ActiveQuery
    {
        $query = CommissionRecord::find()->where(['affiliateId' => $affiliateId]);
        if ($status !== null) {
            $query->andWhere(['status' => $status]);
        }
        return $query;
    }

    public function getCommissionById(int $id): ?CommissionRecord
    {
        return CommissionRecord::findOne($id);
    }

    public function calculateAmount(float $orderSubtotal, float $rate, string $rateType): float
    {
        $amount = match ($rateType) {
            Commission::RATE_TYPE_PERCENTAGE => $orderSubtotal * ($rate / 100),
            Commission::RATE_TYPE_FLAT => $rate,
            default => 0.0,
        };

        return $this->roundMoney($amount);
    }

    /**
     * Round to the active currency's minor unit. Every arithmetic result
     * written to the DB or compared between commissions should pass through
     * this method - drift accumulates from unrounded intermediates.
     */
    public function roundMoney(float $amount): float
    {
        if (class_exists(\craft\commerce\helpers\Currency::class)) {
            return \craft\commerce\helpers\Currency::round($amount);
        }

        return round($amount, 2);
    }

    /**
     * Un-link commissions from a reversed payout so they return to approved
     * status and become eligible for a future payout run.
     */
    public function unlinkCommissionsFromPayout(int $payoutId): int
    {
        return Craft::$app->getDb()->createCommand()
            ->update(
                '{{%kickback_commissions}}',
                ['payoutId' => null, 'status' => Commission::STATUS_APPROVED],
                ['payoutId' => $payoutId, 'status' => Commission::STATUS_PAID],
            )
            ->execute();
    }

    /**
     * Mark all approved commissions for an affiliate as paid, linking them to
     * a payout. Sets datePaid on referrals when all their commissions are paid.
     */
    public function markCommissionsPaidForAffiliate(int $affiliateId, int $payoutId): void
    {
        $now = DateTimeHelper::currentUTCDateTime();
        $els = Craft::$app->getElements();
        $CE = \anvildev\craftkickback\elements\CommissionElement::class;
        $RE = \anvildev\craftkickback\elements\ReferralElement::class;

        $referralIds = [];
        foreach ($CE::find()->affiliateId($affiliateId)->commissionStatus($CE::STATUS_APPROVED)->andWhere(['kickback_commissions.payoutId' => null])->all() as $el) {
            $el->commissionStatus = $CE::STATUS_PAID;
            $el->payoutId = $payoutId;
            $els->saveElement($el, false);
            if ($el->referralId !== null) {
                $referralIds[$el->referralId] = true;
            }
        }

        $terminal = [$CE::STATUS_PAID, $CE::STATUS_REVERSED, $CE::STATUS_REJECTED];
        foreach (array_keys($referralIds) as $rid) {
            if ((int)$CE::find()->referralId($rid)->andWhere(['not in', 'kickback_commissions.status', $terminal])->count() === 0) {
                $ref = $RE::find()->id($rid)->one();
                if ($ref !== null && $ref->referralStatus !== $RE::STATUS_REJECTED) {
                    $ref->referralStatus = $RE::STATUS_PAID;
                    $ref->datePaid = $now;
                    $els->saveElement($ref, false);
                }
            }
        }
    }

    /**
     * @param array<string, mixed>|null $ruleResolutionTrace
     */
    private function saveCommission(
        ReferralRecord $referral,
        AffiliateElement $affiliate,
        float $amount,
        float $rate,
        string $rateType,
        string $ruleApplied,
        int $tier,
        string $currency,
        ?array $ruleResolutionTrace = null,
    ): CommissionRecord {
        $CE = \anvildev\craftkickback\elements\CommissionElement::class;
        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            $approved = $referral->status === Referral::STATUS_APPROVED;
            $status = $approved ? $CE::STATUS_APPROVED : $CE::STATUS_PENDING;

            $element = new $CE();
            $element->referralId = $referral->id;
            $element->affiliateId = $affiliate->id;
            $element->amount = $element->originalAmount = $amount;
            $element->currency = $currency;
            $element->rate = $rate;
            $element->rateType = $rateType;
            $element->ruleApplied = $ruleApplied;
            $encoded = $ruleResolutionTrace !== null ? json_encode($ruleResolutionTrace, JSON_UNESCAPED_SLASHES) : null;
            $element->ruleResolutionTrace = is_string($encoded) ? $encoded : null;
            $element->tier = $tier;
            $element->commissionStatus = $status;
            if ($approved) {
                $element->dateApproved = DateTimeHelper::currentUTCDateTime();
            }

            $beforeEvent = new CommissionEvent(['element' => $element, 'affiliate' => $affiliate]);
            $this->trigger(self::EVENT_BEFORE_CREATE_COMMISSION, $beforeEvent);
            if (!$beforeEvent->isValid) {
                $transaction->rollBack();
                throw new \RuntimeException('Commission creation vetoed by EVENT_BEFORE_CREATE_COMMISSION listener');
            }

            if (!Craft::$app->getElements()->saveElement($element, false)) {
                $transaction->rollBack();
                throw new \RuntimeException('Failed to save commission element');
            }

            /** @var CommissionRecord $commission */
            $commission = CommissionRecord::findOne($element->id);

            if ($approved) {
                KickBack::getInstance()->affiliates->addPendingBalance($affiliate, $amount);
            }

            $transaction->commit();

            Craft::info("Commission #{$commission->id} created: {$commission->amount} {$commission->currency} for affiliate #{$commission->affiliateId} (referral #{$commission->referralId}, tier {$commission->tier})", __METHOD__);

            $this->trigger(self::EVENT_AFTER_CREATE_COMMISSION, new CommissionEvent([
                'commission' => $commission, 'element' => $element, 'affiliate' => $affiliate,
            ]));

            return $commission;
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }
}
