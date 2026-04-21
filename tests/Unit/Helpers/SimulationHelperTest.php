<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Helpers;

use anvildev\craftkickback\helpers\SimulationHelper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SimulationHelperTest extends TestCase
{
    #[Test]
    public function parseWeightedMixParsesValidPairs(): void
    {
        $mix = 'product:25,category:20,tiered:20,bonus:15,group:10,program:10';

        $parsed = SimulationHelper::parseWeightedMix($mix);

        $this->assertSame([
            'product' => 25,
            'category' => 20,
            'tiered' => 20,
            'bonus' => 15,
            'group' => 10,
            'program' => 10,
        ], $parsed);
    }

    #[Test]
    public function parseWeightedMixDropsInvalidValues(): void
    {
        $mix = 'product:25, broken ,foo:-2,bar:0,baz:3';

        $parsed = SimulationHelper::parseWeightedMix($mix);

        $this->assertSame([
            'product' => 25,
            'baz' => 3,
        ], $parsed);
    }

    #[Test]
    public function pickWeightedReturnsNullForEmptySet(): void
    {
        $this->assertNull(SimulationHelper::pickWeighted([]));
    }

    #[Test]
    public function pickWeightedReturnsKnownKey(): void
    {
        $weights = [
            'product' => 25,
            'category' => 20,
            'tiered' => 20,
        ];

        $picked = SimulationHelper::pickWeighted($weights);

        $this->assertContains($picked, array_keys($weights));
    }
}

