<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Services;

use PHPUnit\Framework\TestCase;

/**
 * Regression lock for PayoutService::handleGatewayResult's three-state
 * dispatch. A bug exposed in the PayPal 1.1 re-enable work: the method
 * originally routed every success result through completePayout, even
 * when PayoutResult::pending had set gatewayStatus to STATUS_PENDING.
 * The three paths (success/pending, success/completed, failure) must
 * each have a distinct branch.
 */
class HandleGatewayResultTest extends TestCase
{
    private const SERVICE_FILE = __DIR__ . '/../../../src/services/PayoutService.php';

    public function testPendingBranchLeavesPayoutProcessingAndPersistsBatchId(): void
    {
        $body = $this->extractMethodBody('handleGatewayResult');

        $this->assertStringContainsString(
            'STATUS_PENDING',
            $body,
            'handleGatewayResult must have a distinct branch for the async-pending gateway status.',
        );
        $this->assertStringContainsString(
            'saveElement',
            $body,
            'Pending branch must persist the batch id / transaction id via saveElement.',
        );
        $this->assertStringContainsString(
            'Payout #',
            $body,
            'Pending branch must emit an info log announcing the async handoff.',
        );
    }

    public function testFailureBranchCallsFailPayout(): void
    {
        $body = $this->extractMethodBody('handleGatewayResult');
        $this->assertStringContainsString('failPayout', $body);
    }

    public function testCompletedBranchCallsCompletePayout(): void
    {
        $body = $this->extractMethodBody('handleGatewayResult');
        $this->assertStringContainsString('completePayout', $body);
    }

    private function extractMethodBody(string $name): string
    {
        $source = file_get_contents(self::SERVICE_FILE);
        $this->assertNotFalse($source, self::SERVICE_FILE . ' must be readable');
        $start = strpos($source, "function {$name}(");
        $this->assertNotFalse($start, "Method {$name} must exist");
        $next = strpos($source, 'function ', $start + 1);
        return substr($source, $start, $next === false ? null : $next - $start);
    }
}
