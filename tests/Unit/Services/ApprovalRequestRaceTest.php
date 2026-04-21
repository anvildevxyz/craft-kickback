<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Services;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Regression lock for ApprovalService::request()'s insert-vs-insert race handling.
 *
 * Two concurrent callers requesting approval for the same (targetType, targetId)
 * collide on the unique index. Without the IntegrityException catch, the losing
 * caller surfaces a 500; with it, they re-findOne and return the winning row.
 *
 * Like PayoutQuerySelectListTest, this is a source-inspection regression - it
 * cannot prove the runtime behavior works, but it catches the "someone removed
 * the catch block" refactor without needing a DB bootstrap.
 */
class ApprovalRequestRaceTest extends TestCase
{
    private const SERVICE_FILE = __DIR__ . '/../../../src/services/ApprovalService.php';

    #[Test]
    public function requestCatchesIntegrityException(): void
    {
        $source = file_get_contents(self::SERVICE_FILE);
        $this->assertNotFalse($source, 'ApprovalService.php must be readable');

        $this->assertStringContainsString(
            'use yii\db\IntegrityException;',
            $source,
            'ApprovalService must import IntegrityException to catch the unique-index race.',
        );

        $this->assertStringContainsString(
            'catch (IntegrityException)',
            $source,
            'ApprovalService::request() must catch IntegrityException - concurrent callers racing on the (targetType, targetId) unique index would otherwise surface a 500.',
        );
    }

    #[Test]
    public function requestReFindsOnRaceLoss(): void
    {
        // The catch branch must actually re-read the row, not just swallow the
        // exception. Assert the re-findOne is present and returns the winner.
        $source = file_get_contents(self::SERVICE_FILE);

        $this->assertMatchesRegularExpression(
            '/catch \(IntegrityException\).*?ApprovalRecord::findOne\(/s',
            $source,
            'The IntegrityException catch block must re-findOne the approval so the losing caller still gets a valid record back.',
        );
    }
}
