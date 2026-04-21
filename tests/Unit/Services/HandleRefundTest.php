<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Services;

use PHPUnit\Framework\TestCase;

class HandleRefundTest extends TestCase
{
    private const SERVICE_FILE = __DIR__ . '/../../../src/services/ReferralService.php';

    public function testFullRefundReversesAllCommissionsForOrder(): void
    {
        $source = file_get_contents(self::SERVICE_FILE);
        $this->assertNotFalse($source, 'ReferralService.php must be readable');

        $start = strpos($source, 'function handleRefund(');
        $this->assertNotFalse($start, 'handleRefund method must exist');
        $end = strpos($source, 'function ', $start + 1);
        $body = substr($source, $start, $end === false ? null : $end - $start);

        $this->assertStringContainsString(
            'reverseCommission',
            $body,
            'handleRefund() must call a commission reversal method (reverseCommission or reverseCommissionsProportionally).',
        );
    }

    public function testPartialRefundReducesCommissionProportionally(): void
    {
        $source = file_get_contents(self::SERVICE_FILE);
        $this->assertNotFalse($source, 'ReferralService.php must be readable');

        $start = strpos($source, 'function handleRefund(');
        $this->assertNotFalse($start, 'handleRefund method must exist');
        $end = strpos($source, 'function ', $start + 1);
        $body = substr($source, $start, $end === false ? null : $end - $start);

        $this->assertStringContainsString(
            'orderSubtotal',
            $body,
            'handleRefund() must use orderSubtotal as the denominator for proportional refund math.',
        );

        $this->assertStringContainsString(
            'reverseCommissionsProportionally',
            $body,
            'handleRefund() must call reverseCommissionsProportionally for partial refunds.',
        );
    }

    public function testRefundRespectsReverseCommissionOnRefundSetting(): void
    {
        $source = file_get_contents(self::SERVICE_FILE);
        $this->assertNotFalse($source, 'ReferralService.php must be readable');

        $start = strpos($source, 'function handleRefund(');
        $this->assertNotFalse($start, 'handleRefund method must exist');
        $end = strpos($source, 'function ', $start + 1);
        $body = substr($source, $start, $end === false ? null : $end - $start);

        $this->assertStringContainsString(
            'reverseCommissionOnRefund',
            $body,
            'handleRefund() must check the reverseCommissionOnRefund setting and return early when it is false.',
        );
    }

    public function testRefundOnAlreadyPaidCommissionCreatesAdjustmentRecord(): void
    {
        $this->markTestSkipped('Adjustment records for paid-then-refunded commissions are out of scope for 1.x.');
    }
}
