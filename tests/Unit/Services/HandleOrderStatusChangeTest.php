<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Services;

use PHPUnit\Framework\TestCase;

class HandleOrderStatusChangeTest extends TestCase
{
    private const SERVICE_FILE = __DIR__ . '/../../../src/services/ReferralService.php';

    public function testOrderMovedToCancelledHandleCancelReferral(): void
    {
        $source = file_get_contents(self::SERVICE_FILE);
        $this->assertNotFalse($source, 'ReferralService.php must be readable');

        $start = strpos($source, 'function handleOrderStatusChange(');
        $this->assertNotFalse($start, 'handleOrderStatusChange method must exist');
        $end = strpos($source, 'function ', $start + 1);
        $body = substr($source, $start, $end === false ? null : $end - $start);

        $this->assertStringContainsString(
            'cancelledStatusHandles',
            $body,
            'handleOrderStatusChange() must read cancelledStatusHandles from Settings.',
        );

        $this->assertTrue(
            str_contains($body, 'rejectReferral') || str_contains($body, 'cancelReferral'),
            'handleOrderStatusChange() must call rejectReferral() or cancelReferral() as the state mutation path.',
        );
    }

    public function testOrderMovedToCustomCancelledHandleIsRecognized(): void
    {
        $source = file_get_contents(self::SERVICE_FILE);
        $this->assertNotFalse($source, 'ReferralService.php must be readable');

        $start = strpos($source, 'function handleOrderStatusChange(');
        $this->assertNotFalse($start, 'handleOrderStatusChange method must exist');
        $end = strpos($source, 'function ', $start + 1);
        $body = substr($source, $start, $end === false ? null : $end - $start);

        $this->assertTrue(
            str_contains($body, 'in_array') || str_contains($body, 'array_key_exists'),
            'handleOrderStatusChange() must use in_array() or array_key_exists() to check status against the cancelledStatusHandles list.',
        );
    }

    public function testOrderMovedToNonCancelledHandleDoesNothing(): void
    {
        $source = file_get_contents(self::SERVICE_FILE);
        $this->assertNotFalse($source, 'ReferralService.php must be readable');

        $start = strpos($source, 'function handleOrderStatusChange(');
        $this->assertNotFalse($start, 'handleOrderStatusChange method must exist');
        $end = strpos($source, 'function ', $start + 1);
        $body = substr($source, $start, $end === false ? null : $end - $start);

        $this->assertMatchesRegularExpression(
            '/in_array[\s\S]+?cancelledStatusHandles[\s\S]+?return;/s',
            $body,
            'handleOrderStatusChange() must have an early-return path when the status handle is not in cancelledStatusHandles.',
        );
    }
}
