<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Models;

use anvildev\craftkickback\models\CommissionRule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CommissionRuleTest extends TestCase
{
    #[Test]
    public function defaultPriorityIsZero(): void
    {
        $rule = new CommissionRule();
        $this->assertSame(0, $rule->priority);
    }

    #[Test]
    public function defaultCommissionTypeIsPercentage(): void
    {
        $rule = new CommissionRule();
        $this->assertSame('percentage', $rule->commissionType);
    }

    #[Test]
    public function defaultCommissionRateIsZero(): void
    {
        $rule = new CommissionRule();
        $this->assertSame(0.0, $rule->commissionRate);
    }

    #[Test]
    public function defaultNullableFieldsAreNull(): void
    {
        $rule = new CommissionRule();
        $this->assertNull($rule->id);
        $this->assertNull($rule->targetId);
        $this->assertNull($rule->tierThreshold);
        $this->assertNull($rule->tierLevel);
        $this->assertNull($rule->lookbackDays);
        $this->assertNull($rule->conditions);
    }
}
