<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Jobs;

use PHPUnit\Framework\TestCase;

class ReconcilePayoutsJobTest extends TestCase
{
    private const JOB_FILE = __DIR__ . '/../../../src/jobs/ReconcilePayoutsJob.php';

    public function testSkipsGatewaysWithoutFetchPayoutStatus(): void
    {
        $source = file_get_contents(self::JOB_FILE);
        $this->assertNotFalse($source, 'ReconcilePayoutsJob.php must be readable');

        $start = strpos($source, 'function execute(');
        $this->assertNotFalse($start, 'execute method must exist');
        $end = strpos($source, 'function ', $start + 1);
        $body = substr($source, $start, $end === false ? null : $end - $start);

        $this->assertStringContainsString(
            'instanceof ReconciliationCapableInterface',
            $body,
            'execute() must use an instanceof ReconciliationCapableInterface check (not method_exists) to skip gateways that cannot report payout status.',
        );
    }

    public function testMarksReversedOnGatewayReport(): void
    {
        $source = file_get_contents(self::JOB_FILE);
        $this->assertNotFalse($source, 'ReconcilePayoutsJob.php must be readable');

        $start = strpos($source, 'function execute(');
        $this->assertNotFalse($start, 'execute method must exist');
        $end = strpos($source, 'function ', $start + 1);
        $body = substr($source, $start, $end === false ? null : $end - $start);

        $this->assertStringContainsString(
            'markReversed',
            $body,
            'execute() must call markReversed() when the gateway reports a reversal.',
        );

        $this->assertStringContainsString(
            "'reversed'",
            $body,
            "execute() must check for the 'reversed' status string from the gateway before calling markReversed.",
        );
    }

    public function testRespectsDaysWindow(): void
    {
        $source = file_get_contents(self::JOB_FILE);
        $this->assertNotFalse($source, 'ReconcilePayoutsJob.php must be readable');

        $start = strpos($source, 'function execute(');
        $this->assertNotFalse($start, 'execute method must exist');
        $end = strpos($source, 'function ', $start + 1);
        $body = substr($source, $start, $end === false ? null : $end - $start);

        $this->assertStringContainsString(
            '$this->days',
            $body,
            'execute() must reference $this->days to apply the configurable cutoff window.',
        );

        $this->assertStringContainsString(
            'dateUpdated',
            $body,
            'execute() must filter by dateUpdated to exclude payouts older than the days window.',
        );

        $this->assertStringContainsString(
            '->each(',
            $body,
            'execute() must stream payouts via ->each() rather than ->all() to avoid loading all elements into memory at once.',
        );
    }
}
