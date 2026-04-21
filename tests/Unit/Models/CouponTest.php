<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Models;

use anvildev\craftkickback\models\Coupon;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CouponTest extends TestCase
{
    #[Test]
    public function defaultIsVanityIsFalse(): void
    {
        $coupon = new Coupon();
        $this->assertFalse($coupon->isVanity);
    }

    #[Test]
    public function defaultCodeIsEmptyString(): void
    {
        $coupon = new Coupon();
        $this->assertSame('', $coupon->code);
    }

    #[Test]
    public function defaultNullableFieldsAreNull(): void
    {
        $coupon = new Coupon();
        $this->assertNull($coupon->id);
        $this->assertNull($coupon->affiliateId);
        $this->assertNull($coupon->discountId);
    }
}
