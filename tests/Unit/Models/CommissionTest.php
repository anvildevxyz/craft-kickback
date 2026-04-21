<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Models;

use anvildev\craftkickback\models\Commission;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CommissionTest extends TestCase
{
    #[Test]
    public function allStatusConstantsAreInStatusesArray(): void
    {
        $this->assertContains(Commission::STATUS_PENDING, Commission::STATUSES);
        $this->assertContains(Commission::STATUS_APPROVED, Commission::STATUSES);
        $this->assertContains(Commission::STATUS_PAID, Commission::STATUSES);
        $this->assertContains(Commission::STATUS_REVERSED, Commission::STATUSES);
        $this->assertContains(Commission::STATUS_REJECTED, Commission::STATUSES);
    }

    #[Test]
    public function statusesArrayCountMatchesConstants(): void
    {
        $this->assertCount(5, Commission::STATUSES);
    }

    #[Test]
    public function allRateTypeConstantsAreInRateTypesArray(): void
    {
        $this->assertContains(Commission::RATE_TYPE_PERCENTAGE, Commission::RATE_TYPES);
        $this->assertContains(Commission::RATE_TYPE_FLAT, Commission::RATE_TYPES);
    }

    #[Test]
    public function rateTypesArrayCountMatchesConstants(): void
    {
        $this->assertCount(2, Commission::RATE_TYPES);
    }

    #[Test]
    public function defaultAmountIsZero(): void
    {
        $commission = new Commission();
        $this->assertSame(0.0, $commission->amount);
    }

    #[Test]
    public function defaultStatusIsPending(): void
    {
        $commission = new Commission();
        $this->assertSame('pending', $commission->status);
    }

    #[Test]
    public function defaultTierIsOne(): void
    {
        $commission = new Commission();
        $this->assertSame(1, $commission->tier);
    }
}
