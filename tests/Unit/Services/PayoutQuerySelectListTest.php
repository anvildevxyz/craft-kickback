<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Services;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Regression lock for the payout verification self-verify guard.
 *
 * The self-verify rule depends on PayoutElement::$createdByUserId being hydrated
 * from the DB. PayoutQuery::beforePrepare() uses an explicit select() list, so
 * every column it needs must be named explicitly - dropping one silently nulls
 * the property on read and bypasses the four-eyes rule.
 *
 * This test reads the file source to assert the column is still in the select list.
 * It is not a substitute for an integration test, but it catches the common
 * "someone refactored the select list" regression without needing a DB bootstrap.
 */
class PayoutQuerySelectListTest extends TestCase
{
    private const QUERY_FILE = __DIR__ . '/../../../src/elements/db/PayoutQuery.php';

    #[Test]
    public function selectListIncludesCreatedByUserId(): void
    {
        $source = file_get_contents(self::QUERY_FILE);
        $this->assertNotFalse($source, 'PayoutQuery.php must be readable');

        $this->assertStringContainsString(
            "'kickback_payouts.createdByUserId'",
            $source,
            'PayoutQuery::beforePrepare() must select createdByUserId or the self-verify guard silently bypasses (cross-unit regression from review 2026-04-10).',
        );
    }

    #[Test]
    public function selectListIncludesAffiliateId(): void
    {
        // Sanity check: confirm the test is actually reading the select list
        // and not a stale file or a different method.
        $source = file_get_contents(self::QUERY_FILE);
        $this->assertStringContainsString(
            "'kickback_payouts.affiliateId'",
            $source,
        );
    }
}
