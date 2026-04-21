<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Models;

use anvildev\craftkickback\models\Program;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ProgramTest extends TestCase
{
    #[Test]
    public function allStatusConstantsInStatusesArray(): void
    {
        $this->assertContains(Program::STATUS_ACTIVE, Program::STATUSES);
        $this->assertContains(Program::STATUS_INACTIVE, Program::STATUSES);
        $this->assertContains(Program::STATUS_ARCHIVED, Program::STATUSES);
    }

    #[Test]
    public function statusesArrayCountMatchesConstants(): void
    {
        $this->assertCount(3, Program::STATUSES);
    }

    #[Test]
    public function defaultCommissionRateIsTen(): void
    {
        $program = new Program();
        $this->assertSame(10.0, $program->defaultCommissionRate);
    }

    #[Test]
    public function defaultCommissionTypeIsPercentage(): void
    {
        $program = new Program();
        $this->assertSame('percentage', $program->defaultCommissionType);
    }

    #[Test]
    public function defaultCookieDurationIsThirtyDays(): void
    {
        $program = new Program();
        $this->assertSame(30, $program->cookieDuration);
    }

    #[Test]
    public function defaultStatusIsActive(): void
    {
        $program = new Program();
        $this->assertSame('active', $program->status);
    }
}
