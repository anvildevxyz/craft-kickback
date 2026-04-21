<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Models;

use anvildev\craftkickback\models\AffiliateGroup;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AffiliateGroupTest extends TestCase
{
    #[Test]
    public function defaultCommissionRateIsZero(): void
    {
        $group = new AffiliateGroup();
        $this->assertSame(0.0, $group->commissionRate);
    }

    #[Test]
    public function defaultCommissionTypeIsPercentage(): void
    {
        $group = new AffiliateGroup();
        $this->assertSame('percentage', $group->commissionType);
    }

    #[Test]
    public function defaultSortOrderIsZero(): void
    {
        $group = new AffiliateGroup();
        $this->assertSame(0, $group->sortOrder);
    }

    #[Test]
    public function defaultStringFieldsAreEmpty(): void
    {
        $group = new AffiliateGroup();
        $this->assertSame('', $group->name);
        $this->assertSame('', $group->handle);
    }
}
