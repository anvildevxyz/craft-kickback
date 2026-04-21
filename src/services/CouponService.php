<?php

declare(strict_types=1);

namespace anvildev\craftkickback\services;

use anvildev\craftkickback\elements\AffiliateElement;
use anvildev\craftkickback\elements\ProgramElement;
use anvildev\craftkickback\helpers\UniqueCodeHelper;
use anvildev\craftkickback\KickBack;
use anvildev\craftkickback\records\CouponRecord;
use Craft;
use craft\base\Component;

/**
 * Manages affiliate coupon codes backed by Commerce discounts.
 */
class CouponService extends Component
{
    /**
     * Create an affiliate coupon backed by a Commerce Discount. On Coupon
     * save failure the Discount is rolled back so no orphan remains.
     *
     * @throws \RuntimeException if Commerce is not installed
     */
    public function createAffiliateCoupon(
        AffiliateElement $affiliate,
        string $code,
        float $discountPercent,
        int $maxUses = 0,
    ): ?CouponRecord {
        if (!class_exists(\craft\commerce\Plugin::class)) {
            throw new \RuntimeException('Craft Commerce is required for coupon creation.');
        }

        $this->assertCouponCreationAllowed($affiliate);

        $commerce = \craft\commerce\Plugin::getInstance();
        if ($commerce === null) {
            return null;
        }

        if (CouponRecord::findOne(['code' => $code]) !== null) {
            Craft::warning("Coupon code '{$code}' already exists", __METHOD__);
            return null;
        }

        // Commerce 5 requires a storeId on every Discount (multi-store support).
        // Fall back to the primary store when no current-site store is set
        // (e.g. console/CP requests without a site context).
        $store = $commerce->getStores()->getCurrentStore() ?? $commerce->getStores()->getPrimaryStore();
        if ($store === null) {
            Craft::error("No Commerce store available for coupon '{$code}'", __METHOD__);
            return null;
        }

        $discount = new \craft\commerce\models\Discount();
        $discount->storeId = $store->id;
        $discount->name = "Kickback: {$affiliate->title} ({$code})";
        $discount->description = "Affiliate coupon for {$affiliate->title}";
        // Commerce stores percent discounts as a negative fraction.
        $discount->percentDiscount = -abs($discountPercent) / 100;
        $discount->hasFreeShippingForMatchingItems = false;
        $discount->hasFreeShippingForOrder = false;
        $discount->allPurchasables = true;
        $discount->allCategories = true;
        $discount->enabled = true;
        $discount->stopProcessing = false;
        // Commerce 5 silently refuses to apply a discount via couponCode unless
        // requireCouponCode is true (Discounts.php:460). Without this flag the
        // discount is treated as an "always-on" discount, and the customer-entered
        // coupon code is ignored at checkout.
        $discount->requireCouponCode = true;

        if (!$commerce->getDiscounts()->saveDiscount($discount)) {
            Craft::error("Failed to save Commerce discount for coupon '{$code}'", __METHOD__);
            return null;
        }

        $coupon = new \craft\commerce\models\Coupon();
        $coupon->code = $code;
        $coupon->discountId = $discount->id;
        $coupon->maxUses = $maxUses > 0 ? $maxUses : null;

        if (!$commerce->getCoupons()->saveCoupon($coupon)) {
            $commerce->getDiscounts()->deleteDiscountById($discount->id);
            Craft::error("Failed to save Commerce coupon '{$code}'", __METHOD__);
            return null;
        }

        $record = new CouponRecord();
        $record->affiliateId = $affiliate->id;
        $record->discountId = $discount->id;
        $record->code = $code;
        $record->isVanity = false;
        $record->save(false);

        return $record;
    }

    /**
     * Enforce both the plugin-level master switch and the per-program
     * coupon flag. Affiliates without an assigned program fall back to
     * the global setting only.
     *
     * @throws \RuntimeException with a user-facing message when blocked
     */
    private function assertCouponCreationAllowed(AffiliateElement $affiliate): void
    {
        $settings = KickBack::getInstance()->getSettings();
        if (!$settings->enableCouponCreation) {
            throw new \RuntimeException('Coupon creation is disabled in plugin settings.');
        }

        if ($affiliate->programId === null) {
            return;
        }

        $program = ProgramElement::find()->id($affiliate->programId)->status(null)->one();
        if ($program === null) {
            // Affiliate references a missing program - treat as allowed so
            // a stale FK doesn't block legitimate operations. The integrity
            // problem belongs in a separate audit, not here.
            return;
        }

        if (!$program->enableCouponCreation) {
            throw new \RuntimeException(sprintf(
                'Coupon creation is disabled for program "%s".',
                $program->name,
            ));
        }
    }

    /**
     * Generate N coupons for an affiliate. Uses buildBulkCodes() to
     * produce the code set, then wraps createAffiliateCoupon() in a
     * single DB transaction so partial failure rolls everything back.
     *
     * @return CouponRecord[]
     */
    public function bulkCreateAffiliateCoupons(
        AffiliateElement $affiliate,
        string $prefix,
        int $count,
        float $discountPercent,
        int $maxUses = 0,
    ): array {
        if ($count > 1000) {
            throw new \InvalidArgumentException('Bulk count capped at 1000 per batch.');
        }
        if ($discountPercent < 0 || $discountPercent > 100) {
            throw new \InvalidArgumentException('Discount percent must be between 0 and 100.');
        }
        if ($maxUses < 0) {
            throw new \InvalidArgumentException('Max uses must be zero or positive.');
        }

        $this->assertCouponCreationAllowed($affiliate);

        $codes = self::buildBulkCodes($prefix, $count);

        $existing = CouponRecord::find()
            ->select(['code'])
            ->where(['in', 'code', $codes])
            ->column();

        if (!empty($existing)) {
            throw new \RuntimeException(sprintf(
                'Bulk generation aborted: %d code(s) already exist: %s',
                count($existing),
                implode(', ', $existing),
            ));
        }

        $created = [];
        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            foreach ($codes as $code) {
                $record = $this->createAffiliateCoupon($affiliate, $code, $discountPercent, $maxUses);
                if ($record === null) {
                    throw new \RuntimeException("Failed to create coupon with code '{$code}'");
                }
                $created[] = $record;
            }
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            Craft::error("Bulk coupon creation failed: {$e->getMessage()}", __METHOD__);
            throw $e;
        }

        return $created;
    }

    /**
     * Produces `$prefix` + zero-padded serial 1..$count. Pad width =
     * max(3, digits($count)). Static for unit testing without Craft bootstrap.
     *
     * @return string[]
     */
    public static function buildBulkCodes(string $prefix, int $count): array
    {
        $prefix !== '' || throw new \InvalidArgumentException('Prefix must not be empty.');
        $count > 0 || throw new \InvalidArgumentException('Count must be a positive integer.');

        $w = max(3, strlen((string)$count));
        $codes = [];
        for ($i = 1; $i <= $count; $i++) {
            $codes[] = $prefix . str_pad((string)$i, $w, '0', STR_PAD_LEFT);
        }
        return $codes;
    }

    /**
     * @return CouponRecord[]
     */
    public function getCouponsByAffiliateId(int $affiliateId): array
    {
        /** @var CouponRecord[] */
        return CouponRecord::find()->where(['affiliateId' => $affiliateId])->orderBy(['dateCreated' => SORT_DESC])->all();
    }

    /**
     * Hard-delete a coupon and its Commerce discount. Refuses used coupons.
     *
     * @throws \RuntimeException if the coupon has been used at least once
     */
    public function deleteCoupon(int $couponId): bool
    {
        $record = CouponRecord::findOne($couponId);
        if ($record === null) {
            return false;
        }

        if (class_exists(\craft\commerce\Plugin::class)) {
            $commerce = \craft\commerce\Plugin::getInstance();
            $commerceCoupon = $commerce?->getCoupons()->getCouponByCode($record->code);
            if ($commerceCoupon !== null && $commerceCoupon->uses > 0) {
                throw new \RuntimeException(sprintf(
                    'Coupon "%s" has been used %d time(s) and cannot be hard-deleted. Disable it instead.',
                    $record->code, $commerceCoupon->uses,
                ));
            }
            $commerce?->getDiscounts()->deleteDiscountById($record->discountId);
        }

        return $record->delete() !== false;
    }

    public function disableCoupon(int $couponId): bool
    {
        $record = CouponRecord::findOne($couponId);
        if ($record === null || !class_exists(\craft\commerce\Plugin::class)) {
            return false;
        }

        $commerce = \craft\commerce\Plugin::getInstance();
        $discount = $commerce?->getDiscounts()->getDiscountById($record->discountId);
        if ($discount === null) {
            return false;
        }

        $discount->enabled = false;
        return $commerce->getDiscounts()->saveDiscount($discount);
    }

    public function generateCouponCode(AffiliateElement $affiliate): string
    {
        return strtoupper(UniqueCodeHelper::generate(
            strtoupper($affiliate->referralCode) . '-' . strtoupper(bin2hex(random_bytes(2))),
            fn(string $code) => CouponRecord::findOne(['code' => $code]) !== null,
        ));
    }
}
