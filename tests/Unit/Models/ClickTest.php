<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Models;

use anvildev\craftkickback\models\Click;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ClickTest extends TestCase
{
    #[Test]
    public function defaultIsUniqueIsTrue(): void
    {
        $click = new Click();
        $this->assertTrue($click->isUnique);
    }

    #[Test]
    public function defaultIpIsEmptyString(): void
    {
        $click = new Click();
        $this->assertSame('', $click->ip);
    }

    #[Test]
    public function defaultNullableFieldsAreNull(): void
    {
        $click = new Click();
        $this->assertNull($click->id);
        $this->assertNull($click->affiliateId);
        $this->assertNull($click->userAgent);
        $this->assertNull($click->referrerUrl);
        $this->assertNull($click->subId);
    }
}
