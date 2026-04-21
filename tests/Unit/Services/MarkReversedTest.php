<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Services;

use PHPUnit\Framework\TestCase;

class MarkReversedTest extends TestCase
{
    private const SERVICE_FILE = __DIR__ . '/../../../src/services/PayoutService.php';

    public function testMarkReversedOnCompletedPayoutRestoresBalance(): void
    {
        $source = file_get_contents(self::SERVICE_FILE);
        $this->assertNotFalse($source, 'PayoutService.php must be readable');

        $start = strpos($source, 'function markReversed(');
        $this->assertNotFalse($start, 'markReversed method must exist');
        $end = strpos($source, 'function ', $start + 1);
        $body = substr($source, $start, $end === false ? null : $end - $start);

        $this->assertStringContainsString(
            'addPendingBalance',
            $body,
            'markReversed() must call addPendingBalance to restore the affiliate balance.',
        );

        $this->assertStringContainsString(
            'unlinkCommissionsFromPayout',
            $body,
            'markReversed() must call unlinkCommissionsFromPayout to detach commissions from the reversed payout.',
        );

        $this->assertStringContainsString(
            'beginTransaction()',
            $body,
            'markReversed() must wrap its mutations in a DB transaction.',
        );

        $this->assertStringContainsString(
            '$transaction->commit()',
            $body,
            'markReversed() must commit its transaction on the happy path.',
        );

        $this->assertStringContainsString(
            '$transaction->rollBack()',
            $body,
            'markReversed() must roll back on failure.',
        );
    }

    public function testMarkReversedIsIdempotent(): void
    {
        $source = file_get_contents(self::SERVICE_FILE);
        $this->assertNotFalse($source, 'PayoutService.php must be readable');

        $start = strpos($source, 'function markReversed(');
        $this->assertNotFalse($start, 'markReversed method must exist');
        $end = strpos($source, 'function ', $start + 1);
        $body = substr($source, $start, $end === false ? null : $end - $start);

        $this->assertMatchesRegularExpression(
            '/->update\([\s\S]+?STATUS_COMPLETED/s',
            $body,
            'markReversed() must use a status-conditioned UPDATE with STATUS_COMPLETED in the WHERE clause to ensure idempotence.',
        );
    }

    public function testMarkReversedNoOpOnNonCompletedPayout(): void
    {
        $source = file_get_contents(self::SERVICE_FILE);
        $this->assertNotFalse($source, 'PayoutService.php must be readable');

        $start = strpos($source, 'function markReversed(');
        $this->assertNotFalse($start, 'markReversed method must exist');
        $end = strpos($source, 'function ', $start + 1);
        $body = substr($source, $start, $end === false ? null : $end - $start);

        $this->assertMatchesRegularExpression(
            '/payoutStatus\s*!==\s*PayoutElement::STATUS_COMPLETED/',
            $body,
            'markReversed() must have an early-return guard checking that payoutStatus is STATUS_COMPLETED.',
        );
    }
}
