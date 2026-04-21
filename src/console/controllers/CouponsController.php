<?php

declare(strict_types=1);

namespace anvildev\craftkickback\console\controllers;

use anvildev\craftkickback\elements\AffiliateElement;
use anvildev\craftkickback\KickBack;
use anvildev\craftkickback\services\CouponService;
use craft\console\Controller;
use yii\console\ExitCode;

/**
 * Console commands for affiliate coupon management.
 */
class CouponsController extends Controller
{
    public string $prefix = '';
    public int $count = 0;
    public int $affiliate = 0;
    public float $discount = 10.0;
    public int $maxUses = 0;
    public bool $dryRun = false;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), [
            'prefix',
            'count',
            'affiliate',
            'discount',
            'maxUses',
            'dryRun',
        ]);
    }

    /**
     * Generate N coupons for a single affiliate.
     *
     * Example:
     *   php craft kickback/coupons/bulk-generate \
     *     --prefix=LAUNCH --count=100 --affiliate=42 --discount=10
     */
    public function actionBulkGenerate(): int
    {
        if ($this->prefix === '' || $this->count <= 0 || $this->affiliate <= 0) {
            $this->stderr("Required: --prefix=STR --count=N --affiliate=ID [--discount=10] [--maxUses=0]\n");
            return ExitCode::USAGE;
        }

        $affiliate = AffiliateElement::find()->id($this->affiliate)->one();
        if (!$affiliate instanceof AffiliateElement) {
            $this->stderr("Affiliate #{$this->affiliate} not found.\n");
            return ExitCode::DATAERR;
        }

        if ($this->dryRun) {
            $codes = CouponService::buildBulkCodes($this->prefix, $this->count);
            $this->stdout(sprintf(
                "Would create %d coupons for %s:\n  %s\n  ...\n  %s\n",
                count($codes),
                $affiliate->title,
                $codes[0],
                $codes[array_key_last($codes)],
            ));
            return ExitCode::OK;
        }

        try {
            $created = KickBack::getInstance()->coupons->bulkCreateAffiliateCoupons(
                $affiliate,
                $this->prefix,
                $this->count,
                $this->discount,
                $this->maxUses,
            );
        } catch (\Throwable $e) {
            $this->stderr("Bulk generation failed: {$e->getMessage()}\n");
            return ExitCode::SOFTWARE;
        }

        $this->stdout(sprintf(
            "Created %d coupons for %s (%s → %s).\n",
            count($created),
            $affiliate->title,
            $created[0]->code,
            $created[count($created) - 1]->code,
        ));
        return ExitCode::OK;
    }
}
