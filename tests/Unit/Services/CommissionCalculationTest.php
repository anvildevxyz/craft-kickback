<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Services;

use anvildev\craftkickback\services\CommissionService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CommissionCalculationTest extends TestCase
{
    private CommissionService $service;

    protected function setUp(): void
    {
        $this->service = new CommissionService();
    }

    #[Test]
    public function percentageCalculation(): void
    {
        $amount = $this->service->calculateAmount(100.0, 10.0, 'percentage');
        $this->assertSame(10.0, $amount);
    }

    #[Test]
    public function percentageWithDecimalRate(): void
    {
        $amount = $this->service->calculateAmount(200.0, 7.5, 'percentage');
        $this->assertSame(15.0, $amount);
    }

    #[Test]
    public function percentageWithSmallSubtotal(): void
    {
        $amount = $this->service->calculateAmount(1.00, 10.0, 'percentage');
        $this->assertSame(0.1, $amount);
    }

    #[Test]
    public function flatRateIgnoresSubtotal(): void
    {
        $amount = $this->service->calculateAmount(500.0, 25.0, 'flat');
        $this->assertSame(25.0, $amount);
    }

    #[Test]
    public function flatRateReturnsRateRegardlessOfSubtotal(): void
    {
        $small = $this->service->calculateAmount(1.0, 25.0, 'flat');
        $large = $this->service->calculateAmount(10000.0, 25.0, 'flat');
        $this->assertSame($small, $large);
    }

    #[Test]
    public function zeroSubtotalWithPercentageReturnsZero(): void
    {
        $amount = $this->service->calculateAmount(0.0, 10.0, 'percentage');
        $this->assertSame(0.0, $amount);
    }

    #[Test]
    public function zeroRateWithPercentageReturnsZero(): void
    {
        $amount = $this->service->calculateAmount(100.0, 0.0, 'percentage');
        $this->assertSame(0.0, $amount);
    }

    #[Test]
    public function unknownRateTypeReturnsZero(): void
    {
        $amount = $this->service->calculateAmount(100.0, 10.0, 'unknown');
        $this->assertSame(0.0, $amount);
    }

    #[Test]
    public function emptyRateTypeReturnsZero(): void
    {
        $amount = $this->service->calculateAmount(100.0, 10.0, '');
        $this->assertSame(0.0, $amount);
    }

    #[Test]
    public function roundingToCurrencyMinorUnit(): void
    {
        // 33.33% of $100.01 = $33.333333... should round to the currency's
        // minor unit. Commerce's Currency::round uses the primary currency's
        // precision (2 decimals for USD, 0 for JPY, etc.); in the unit test
        // environment Commerce isn't bootstrapped so the service falls back
        // to 2-decimal rounding. Either way the result is a clean cent
        // amount - storing 33.3333 in the DB and then re-reading it as a
        // float is the drift source C2 closed.
        $amount = $this->service->calculateAmount(100.01, 33.33, 'percentage');
        $this->assertSame(33.33, $amount);
    }
}
