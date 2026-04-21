<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Elements;

use anvildev\craftkickback\elements\AffiliateElement;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AffiliateElementTierLevelTest extends TestCase
{
    #[Test]
    public function defaultsToOneWithoutParent(): void
    {
        $this->assertSame(1, AffiliateElement::calculateTierLevel(null));
    }

    #[Test]
    public function incrementsFromParentTierLevel(): void
    {
        $this->assertSame(2, AffiliateElement::calculateTierLevel(1));
        $this->assertSame(3, AffiliateElement::calculateTierLevel(2));
        $this->assertSame(4, AffiliateElement::calculateTierLevel(3));
    }

    #[Test]
    public function tierOneParentProducesTierTwo(): void
    {
        $this->assertSame(2, AffiliateElement::calculateTierLevel(1));
    }

    #[Test]
    public function highTierParentStillIncrements(): void
    {
        $this->assertSame(11, AffiliateElement::calculateTierLevel(10));
    }
}
