<?php

declare(strict_types=1);

namespace anvildev\craftkickback\services;

use anvildev\craftkickback\elements\AffiliateElement;
use anvildev\craftkickback\elements\ReferralElement;
use anvildev\craftkickback\events\ReferralEvent;
use anvildev\craftkickback\KickBack;
use anvildev\craftkickback\models\Commission;
use anvildev\craftkickback\models\Referral;
use anvildev\craftkickback\models\Settings;
use anvildev\craftkickback\records\ClickRecord;
use anvildev\craftkickback\records\CustomerLinkRecord;
use anvildev\craftkickback\records\ReferralRecord;
use Craft;
use craft\base\Component;
use craft\commerce\elements\Order;
use craft\helpers\DateTimeHelper;

/**
 * Processes order attribution, manages referral lifecycle, and handles refunds and cancellations.
 */
class ReferralService extends Component
{
    public const EVENT_BEFORE_CREATE_REFERRAL = 'beforeCreateReferral';
    public const EVENT_AFTER_CREATE_REFERRAL = 'afterCreateReferral';
    public const EVENT_BEFORE_APPROVE_REFERRAL = 'beforeApproveReferral';
    public const EVENT_AFTER_APPROVE_REFERRAL = 'afterApproveReferral';
    public const EVENT_BEFORE_REJECT_REFERRAL = 'beforeRejectReferral';
    public const EVENT_AFTER_REJECT_REFERRAL = 'afterRejectReferral';

    /**
     * Denormalize click metadata onto a referral element. Keeps subId
     * queryable without joining kickback_clicks on every report.
     *
     * Takes primitive clickId + subId rather than a ClickRecord so the
     * method is unit-testable without bootstrapping Yii's ActiveRecord
     * attribute schema.
     */
    public static function applySubIdFromClick(
        ReferralElement $element,
        int $clickId,
        ?string $subId,
    ): void {
        $element->clickId = $clickId;
        $element->subId = $subId;
    }

    /**
     * Process a completed Commerce order for affiliate attribution.
     */
    public function processOrder(Order $order): ?ReferralRecord
    {
        $plugin = KickBack::getInstance();
        $settings = $plugin->getSettings();
        $resolutionTrace = [
            'attributionModel' => $settings->attributionModel,
            'attempts' => [],
        ];

        if ($this->orderHasReferral($order->id)) {
            return null;
        }

        $attribution = null;
        if ($settings->enableLifetimeCommissions) {
            $attribution = $this->resolveFromCustomerLink($order);
            $resolutionTrace['attempts'][] = [
                'source' => Referral::ATTRIBUTION_LIFETIME_CUSTOMER,
                'matched' => $attribution !== null,
            ];
        }
        if ($attribution === null && $settings->enableCouponTracking) {
            $attribution = $this->resolveFromCoupon($order);
            $resolutionTrace['attempts'][] = [
                'source' => Referral::ATTRIBUTION_COUPON,
                'matched' => $attribution !== null,
                'couponCode' => $order->couponCode ?: null,
            ];
        }
        if ($attribution === null && $settings->attributionModel === Settings::ATTRIBUTION_MODEL_LINEAR) {
            $allAttributions = $plugin->tracking->resolveAllAffiliates();
            $resolutionTrace['attempts'][] = [
                'source' => 'linear_cookie_chain',
                'matched' => !empty($allAttributions),
                'count' => count($allAttributions),
            ];
            if (!empty($allAttributions)) {
                return $this->processLinearAttribution($order, $allAttributions, $settings, $resolutionTrace);
            }
        }
        if ($attribution === null) {
            $attribution = $plugin->tracking->resolveAffiliate();
            $resolutionTrace['attempts'][] = [
                'source' => Referral::ATTRIBUTION_COOKIE,
                'matched' => $attribution !== null,
            ];
        }
        if ($attribution === null) {
            return null;
        }

        /** @var AffiliateElement $affiliate */
        $affiliate = $attribution['affiliate'];
        $clickId = $attribution['clickId'] ?? null;
        $method = $attribution['method'];
        $couponCode = $attribution['couponCode'] ?? null;

        if ($affiliate->affiliateStatus !== AffiliateElement::STATUS_ACTIVE) {
            return null;
        }

        if ($order->getCustomer()?->id === $affiliate->userId) {
            $program = $plugin->programs->getProgramById($affiliate->programId);
            if ($program === null || !$program->allowSelfReferral) {
                return null;
            }
        }

        $orderSubtotal = $this->calculateOrderSubtotal($order, $settings);

        $resolutionTrace['resolved'] = [
            'method' => $method,
            'affiliateId' => $affiliate->id,
            'clickId' => $clickId,
            'couponCode' => $couponCode,
            'orderSubtotal' => $orderSubtotal,
        ];

        return $this->processAttribution(
            $order,
            $affiliate,
            $orderSubtotal,
            $clickId,
            $method,
            $couponCode,
            $settings,
            $resolutionTrace,
        );
    }

    /**
     * @param array<string, mixed>|null $referralResolutionTrace
     */
    public function createReferral(
        AffiliateElement $affiliate,
        Order $order,
        float $orderSubtotal,
        ?int $clickId,
        string $method,
        ?string $couponCode,
        ?array $referralResolutionTrace = null,
    ): ?ReferralRecord {
        $settings = KickBack::getInstance()->getSettings();

        $event = new ReferralEvent(['affiliate' => $affiliate]);
        $this->trigger(self::EVENT_BEFORE_CREATE_REFERRAL, $event);
        if (!$event->isValid) {
            return null;
        }

        $element = new ReferralElement();
        $element->affiliateId = $affiliate->id;
        $element->programId = $affiliate->programId;
        $element->orderId = $order->id;
        if ($clickId !== null) {
            $click = ClickRecord::findOne(['id' => $clickId]);
            if ($click !== null) {
                self::applySubIdFromClick($element, (int)$click->id, $click->subId);
            } else {
                $element->clickId = $clickId;
            }
        }
        $element->customerEmail = $order->email;
        $element->customerId = $order->getCustomer()?->id;
        $element->orderSubtotal = $orderSubtotal;
        $element->attributionMethod = $method;
        $element->couponCode = $couponCode;
        $encodedTrace = $referralResolutionTrace !== null
            ? json_encode($referralResolutionTrace, JSON_UNESCAPED_SLASHES)
            : null;
        $element->referralResolutionTrace = is_string($encodedTrace) ? $encodedTrace : null;

        if ($settings->autoApproveReferrals && $settings->holdPeriodDays === 0) {
            $element->referralStatus = \anvildev\craftkickback\elements\ReferralElement::STATUS_APPROVED;
            $element->dateApproved = DateTimeHelper::currentUTCDateTime();
        } else {
            $element->referralStatus = \anvildev\craftkickback\elements\ReferralElement::STATUS_PENDING;
        }

        if (!Craft::$app->getElements()->saveElement($element, false)) {
            return null;
        }

        $referral = ReferralRecord::findOne($element->id);
        if ($referral === null) {
            return null;
        }

        $this->trigger(self::EVENT_AFTER_CREATE_REFERRAL, new ReferralEvent([
            'affiliate' => $affiliate,
            'referral' => $referral,
            'isNew' => true,
        ]));

        return $referral;
    }

    public function orderHasReferral(int $orderId): bool
    {
        return ReferralRecord::find()->where(['orderId' => $orderId])->exists();
    }

    /**
     * @return ReferralRecord[]
     */
    public function getReferralsByAffiliateId(
        int $affiliateId,
        ?string $status = null,
        ?int $limit = null,
        ?int $offset = null,
        ?string $subId = null,
    ): array {
        $query = $this->affiliateReferralQuery($affiliateId, $status, $subId);
        $limit !== null && $query->limit($limit);
        $offset !== null && $query->offset($offset);
        /** @var ReferralRecord[] */
        return $query->orderBy(['dateCreated' => SORT_DESC])->all();
    }

    public function countReferralsByAffiliateId(int $affiliateId, ?string $status = null, ?string $subId = null): int
    {
        return (int)$this->affiliateReferralQuery($affiliateId, $status, $subId)->count();
    }

    private function affiliateReferralQuery(int $affiliateId, ?string $status, ?string $subId): \yii\db\ActiveQuery
    {
        $query = ReferralRecord::find()->where(['affiliateId' => $affiliateId]);
        if ($status !== null) {
            $query->andWhere(['status' => $status]);
        }
        if ($subId !== null && $subId !== '') {
            $query->andWhere(['subId' => $subId]);
        }
        return $query;
    }

    public function getReferralById(int $id): ?ReferralRecord
    {
        return ReferralRecord::findOne($id);
    }

    public function approveReferral(ReferralRecord $referral): bool
    {
        return $this->transitionReferral(
            $referral,
            \anvildev\craftkickback\elements\ReferralElement::STATUS_APPROVED,
            self::EVENT_BEFORE_APPROVE_REFERRAL,
            self::EVENT_AFTER_APPROVE_REFERRAL,
            stampApproved: true,
        );
    }

    public function rejectReferral(ReferralRecord $referral): bool
    {
        return $this->transitionReferral(
            $referral,
            \anvildev\craftkickback\elements\ReferralElement::STATUS_REJECTED,
            self::EVENT_BEFORE_REJECT_REFERRAL,
            self::EVENT_AFTER_REJECT_REFERRAL,
            stampApproved: false,
        );
    }

    private function transitionReferral(
        ReferralRecord $referral,
        string $targetStatus,
        string $beforeEvent,
        string $afterEvent,
        bool $stampApproved,
    ): bool {
        $affiliate = KickBack::getInstance()->affiliates->getAffiliateById($referral->affiliateId);

        $event = new ReferralEvent(['affiliate' => $affiliate]);
        $this->trigger($beforeEvent, $event);
        if (!$event->isValid) {
            return false;
        }

        $element = \anvildev\craftkickback\elements\ReferralElement::find()->id($referral->id)->one();
        if ($element === null) {
            return false;
        }

        $element->referralStatus = $targetStatus;
        if ($stampApproved) {
            $element->dateApproved = DateTimeHelper::currentUTCDateTime();
        }

        if (!Craft::$app->getElements()->saveElement($element, false)) {
            return false;
        }

        $referral->refresh();

        $this->trigger($afterEvent, new ReferralEvent(['affiliate' => $affiliate]));
        return true;
    }

    /**
     * Handle a Commerce refund event. Sums every successful refund transaction
     * on the order so reverseCommissionsProportionally (which reads the
     * immutable originalAmount) stays idempotent across multiple partial refunds.
     */
    public function handleRefund(\craft\commerce\models\Transaction $refundTransaction): void
    {
        $settings = KickBack::getInstance()->getSettings();
        if (!$settings->reverseCommissionOnRefund) {
            return;
        }

        $orderId = $refundTransaction->orderId;
        if ($orderId === null) {
            return;
        }

        $referral = ReferralRecord::findOne(['orderId' => $orderId]);
        if ($referral === null || $referral->status === Referral::STATUS_REJECTED) {
            return;
        }

        $commissions = KickBack::getInstance()->commissions->getCommissionsByReferralId($referral->id);
        if (empty($commissions)) {
            return;
        }

        $orderTotal = (float)$referral->orderSubtotal;
        if ($orderTotal <= 0) {
            return;
        }

        $order = $refundTransaction->getOrder();
        $cumulativeRefunded = 0.0;
        if ($order !== null) {
            foreach ($order->getTransactions() as $tx) {
                if ($tx->type === \craft\commerce\records\Transaction::TYPE_REFUND
                    && $tx->status === \craft\commerce\records\Transaction::STATUS_SUCCESS
                ) {
                    $cumulativeRefunded += (float)$tx->paymentAmount;
                }
            }
        } else {
            $cumulativeRefunded = (float)$refundTransaction->paymentAmount;
        }

        $refundRatio = min($cumulativeRefunded / $orderTotal, 1.0);

        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            if ($refundRatio >= 0.95) {
                foreach ($commissions as $commission) {
                    KickBack::getInstance()->commissions->reverseCommission($commission);
                }
                $this->rejectReferral($referral);
            } else {
                KickBack::getInstance()->commissions->reverseCommissionsProportionally($commissions, $refundRatio);
            }
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        Craft::info("Processed refund for order #{$orderId}: ratio={$refundRatio}", __METHOD__);
    }

    /**
     * Reject the referral and reverse its commissions when the order moves
     * into a status listed in settings.cancelledStatusHandles.
     */
    public function handleOrderStatusChange(\craft\commerce\elements\Order $order): void
    {
        $settings = KickBack::getInstance()->getSettings();
        $orderStatus = $order->getOrderStatus();
        if ($orderStatus === null || !in_array($orderStatus->handle, $settings->cancelledStatusHandles, true)) {
            return;
        }

        $referral = ReferralRecord::findOne(['orderId' => $order->id]);
        if ($referral === null || $referral->status === Referral::STATUS_REJECTED) {
            return;
        }

        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            $commissions = KickBack::getInstance()->commissions->getCommissionsByReferralId($referral->id);
            foreach ($commissions as $commission) {
                if (!in_array($commission->status, [Commission::STATUS_REVERSED, Commission::STATUS_REJECTED], true)) {
                    KickBack::getInstance()->commissions->reverseCommission($commission);
                }
            }
            $this->rejectReferral($referral);
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        Craft::info(
            "Rejected referral #{$referral->id} due to order cancellation (status: {$orderStatus->handle})",
            __METHOD__,
        );
    }

    private function calculateOrderSubtotal(Order $order, Settings $settings): float
    {
        return max(0,
            (float)$order->getItemSubtotal()
            + ($settings->excludeShippingFromCommission ? 0 : (float)$order->getTotalShippingCost())
            + ($settings->excludeTaxFromCommission ? 0 : (float)$order->getTotalTax()),
        );
    }

    /**
     * @return array{affiliate: AffiliateElement, clickId: null, method: string}|null
     */
    private function resolveFromCustomerLink(Order $order): ?array
    {
        $cid = $order->getCustomer()?->id;
        $link = ($cid !== null ? CustomerLinkRecord::findOne(['customerId' => $cid]) : null)
            ?? (!empty($order->email) ? CustomerLinkRecord::findOne(['customerEmail' => $order->email]) : null);

        $affiliate = $link !== null ? KickBack::getInstance()->affiliates->getAffiliateById($link->affiliateId) : null;
        return $affiliate !== null && $affiliate->affiliateStatus === AffiliateElement::STATUS_ACTIVE
            ? ['affiliate' => $affiliate, 'clickId' => null, 'method' => Referral::ATTRIBUTION_LIFETIME_CUSTOMER]
            : null;
    }

    /**
     * @return array{affiliate: AffiliateElement, clickId: null, method: string, couponCode: string}|null
     */
    private function resolveFromCoupon(Order $order): ?array
    {
        $code = $order->couponCode;
        if (empty($code)) {
            return null;
        }
        $affiliate = KickBack::getInstance()->tracking->resolveAffiliateFromCoupon($code);
        return $affiliate !== null
            ? ['affiliate' => $affiliate, 'clickId' => null, 'method' => Referral::ATTRIBUTION_COUPON, 'couponCode' => $code]
            : null;
    }

    /**
     * @param array<string, mixed>|null $referralResolutionTrace
     */
    private function processAttribution(
        Order $order,
        AffiliateElement $affiliate,
        float $orderSubtotal,
        ?int $clickId,
        string $method,
        ?string $couponCode,
        \anvildev\craftkickback\models\Settings $settings,
        ?array $referralResolutionTrace = null,
    ): ?ReferralRecord {
        $plugin = KickBack::getInstance();

        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            $referral = $this->createReferral(
                $affiliate,
                $order,
                $orderSubtotal,
                $clickId,
                $method,
                $couponCode,
                $referralResolutionTrace,
            );
            if ($referral === null) {
                $transaction->rollBack();
                return null;
            }

            if ($settings->enableLifetimeCommissions && $method !== Referral::ATTRIBUTION_LIFETIME_CUSTOMER) {
                $this->linkCustomer($affiliate, $order);
            }

            $plugin->affiliates->incrementReferralCount($affiliate);
            $plugin->tracking->clearReferralCookie();

            if ($settings->enableFraudDetection) {
                $fraudFlags = $plugin->fraud->evaluateReferral($referral);
                if (!empty($fraudFlags) && $settings->fraudAutoFlag) {
                    $plugin->fraud->flagReferral($referral, $fraudFlags);
                    Craft::warning("Referral #{$referral->id} flagged for fraud: " . implode(', ', $fraudFlags), __METHOD__);
                }
            }

            $plugin->commissions->createCommission($referral, $affiliate, $order, $order->currency);

            $transaction->commit();
            return $referral;
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Split the commission equally across all affiliates who touched the customer.
     *
     * @param array<array{affiliate: AffiliateElement, clickId: int, method: string}> $attributions
     * @param array<string, mixed>|null $parentResolutionTrace
     */
    private function processLinearAttribution(
        Order $order,
        array $attributions,
        \anvildev\craftkickback\models\Settings $settings,
        ?array $parentResolutionTrace = null,
    ): ?ReferralRecord {
        $plugin = KickBack::getInstance();
        $orderSubtotal = $this->calculateOrderSubtotal($order, $settings);

        $attributions = array_values(array_filter($attributions, function(array $attr) use ($order, $plugin): bool {
            /** @var AffiliateElement $affiliate */
            $affiliate = $attr['affiliate'];
            if ($affiliate->affiliateStatus !== AffiliateElement::STATUS_ACTIVE) {
                return false;
            }
            if ($order->getCustomer()?->id === $affiliate->userId) {
                $program = $plugin->programs->getProgramById($affiliate->programId);
                return $program !== null && $program->allowSelfReferral;
            }
            return true;
        }));

        if (empty($attributions)) {
            return null;
        }

        $splitFactor = 1.0 / count($attributions);
        $splitSubtotal = $plugin->commissions->roundMoney($orderSubtotal / count($attributions));
        $firstReferral = null;

        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            foreach ($attributions as $attr) {
                /** @var AffiliateElement $affiliate */
                $affiliate = $attr['affiliate'];
                $clickId = $attr['clickId'];

                if ($affiliate->affiliateStatus !== AffiliateElement::STATUS_ACTIVE) {
                    continue;
                }

                $linearTrace = $parentResolutionTrace ?? ['attempts' => []];
                $linearTrace['resolved'] = [
                    'method' => Referral::ATTRIBUTION_COOKIE,
                    'affiliateId' => $affiliate->id,
                    'clickId' => $clickId,
                    'splitFactor' => $splitFactor,
                    'splitSubtotal' => $splitSubtotal,
                    'attributionCount' => count($attributions),
                ];

                $referral = $this->createReferral(
                    $affiliate,
                    $order,
                    $splitSubtotal,
                    $clickId,
                    Referral::ATTRIBUTION_COOKIE,
                    null,
                    $linearTrace,
                );
                if ($referral === null) {
                    continue;
                }

                $firstReferral ??= $referral;
                $plugin->affiliates->incrementReferralCount($affiliate);

                if ($settings->enableFraudDetection) {
                    $fraudFlags = $plugin->fraud->evaluateReferral($referral);
                    if (!empty($fraudFlags) && $settings->fraudAutoFlag) {
                        $plugin->fraud->flagReferral($referral, $fraudFlags);
                    }
                }

                $plugin->commissions->createCommission($referral, $affiliate, $order, $order->currency, $splitFactor);
            }

            if ($settings->enableLifetimeCommissions && $firstReferral !== null) {
                $this->linkCustomer($attributions[0]['affiliate'], $order);
            }

            $plugin->tracking->clearReferralCookie();
            $transaction->commit();
            return $firstReferral;
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    private function linkCustomer(AffiliateElement $affiliate, Order $order): void
    {
        if (empty($order->email)) {
            return;
        }

        $cid = $order->getCustomer()?->id;
        $existing = ($cid !== null ? CustomerLinkRecord::findOne(['customerId' => $cid]) : null)
            ?? CustomerLinkRecord::findOne(['customerEmail' => $order->email]);

        if ($existing !== null) {
            if ($existing->customerId === null && $cid !== null) {
                $existing->customerId = $cid;
                $existing->save(false);
            }
            return;
        }

        $link = new CustomerLinkRecord();
        $link->affiliateId = $affiliate->id;
        $link->customerEmail = $order->email;
        $link->customerId = $cid;
        $link->save(false);
    }
}
