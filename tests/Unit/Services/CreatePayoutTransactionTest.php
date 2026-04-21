<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Services;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Regression lock for PayoutService::createPayout()'s transaction boundary.
 *
 * The element save and the approval request must be atomic - without the
 * transaction, a failed approval insert leaves the payout stranded in pending
 * state with no approval row, and processPayout blocks it forever because
 * isVerifiedIfRequired() sees a missing approval.
 *
 * Source-inspection regression (matches PayoutQuerySelectListTest style):
 * locks in the transaction structure without needing a DB bootstrap.
 */
class CreatePayoutTransactionTest extends TestCase
{
    private const SERVICE_FILE = __DIR__ . '/../../../src/services/PayoutService.php';

    #[Test]
    public function createPayoutOpensTransactionBeforeSave(): void
    {
        $source = file_get_contents(self::SERVICE_FILE);
        $this->assertNotFalse($source, 'PayoutService.php must be readable');

        // Extract the createPayout method body so the other methods'
        // transactions (completePayout already uses beginTransaction) don't
        // cause a false positive.
        $start = strpos($source, 'function createPayout(');
        $this->assertNotFalse($start, 'createPayout method must exist');
        $end = strpos($source, 'function ', $start + 1);
        $body = substr($source, $start, $end === false ? null : $end - $start);

        $this->assertStringContainsString(
            'beginTransaction()',
            $body,
            'createPayout() must open a DB transaction so the element save and approval request are atomic.',
        );

        $this->assertStringContainsString(
            '$transaction->commit()',
            $body,
            'createPayout() must commit its transaction on the happy path.',
        );

        $this->assertStringContainsString(
            '$transaction->rollBack()',
            $body,
            'createPayout() must roll back on failure - otherwise a pending payout with no approval row is stranded forever.',
        );
    }

    #[Test]
    public function createPayoutRollsBackOnSaveFailure(): void
    {
        // If saveElement returns false (validation failure etc.), createPayout
        // must rollback rather than fall through to the approval request.
        $source = file_get_contents(self::SERVICE_FILE);

        $this->assertMatchesRegularExpression(
            '/if \(!Craft::\$app->getElements\(\)->saveElement\(\$payout\)\) \{\s*\$transaction->rollBack\(\);\s*return null;/s',
            $source,
            'createPayout() must roll back the transaction when saveElement() returns false, before bailing out.',
        );
    }
}
