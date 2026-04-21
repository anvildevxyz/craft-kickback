<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Services;

use anvildev\craftkickback\services\CouponService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CouponServiceBulkTest extends TestCase
{
    #[Test]
    public function buildsExpectedCodesFromPrefixAndCount(): void
    {
        $codes = CouponService::buildBulkCodes('LAUNCH', 5);

        self::assertCount(5, $codes);
        self::assertSame('LAUNCH001', $codes[0]);
        self::assertSame('LAUNCH005', $codes[4]);
    }

    #[Test]
    public function zeroPadsToWidthOfCount(): void
    {
        $codes = CouponService::buildBulkCodes('SPRING', 120);

        self::assertSame('SPRING001', $codes[0]);
        self::assertSame('SPRING120', $codes[119]);
    }

    #[Test]
    public function rejectsEmptyPrefix(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CouponService::buildBulkCodes('', 5);
    }

    #[Test]
    public function rejectsNonPositiveCount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CouponService::buildBulkCodes('LAUNCH', 0);
    }
}
