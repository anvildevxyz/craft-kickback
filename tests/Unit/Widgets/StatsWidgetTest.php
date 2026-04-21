<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Widgets;

use anvildev\craftkickback\widgets\StatsWidget;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class StatsWidgetTest extends TestCase
{
    #[Test]
    public function extendsBaseWidget(): void
    {
        $this->assertTrue(is_subclass_of(StatsWidget::class, \craft\base\Widget::class));
    }

    #[Test]
    public function iconReturnsString(): void
    {
        $this->assertSame('chart-bar', StatsWidget::icon());
    }
}
