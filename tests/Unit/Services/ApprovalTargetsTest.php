<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Services;

use anvildev\craftkickback\services\approvals\AffiliateApprovalTarget;
use anvildev\craftkickback\services\approvals\ApprovalTargetInterface;
use anvildev\craftkickback\services\approvals\CommissionApprovalTarget;
use anvildev\craftkickback\services\approvals\PayoutApprovalTarget;
use PHPUnit\Framework\TestCase;

class ApprovalTargetsTest extends TestCase
{
    public function testPayoutTargetImplementsInterface(): void
    {
        $this->assertTrue(
            is_subclass_of(PayoutApprovalTarget::class, ApprovalTargetInterface::class),
            'PayoutApprovalTarget must implement ApprovalTargetInterface',
        );
    }

    public function testAffiliateTargetImplementsInterface(): void
    {
        $this->assertTrue(
            is_subclass_of(AffiliateApprovalTarget::class, ApprovalTargetInterface::class),
            'AffiliateApprovalTarget must implement ApprovalTargetInterface',
        );
    }

    public function testCommissionTargetImplementsInterface(): void
    {
        $this->assertTrue(
            is_subclass_of(CommissionApprovalTarget::class, ApprovalTargetInterface::class),
            'CommissionApprovalTarget must implement ApprovalTargetInterface',
        );
    }

    public function testAffiliateDisplayNameFallsBackOnMissingRecord(): void
    {
        $source = file_get_contents(
            __DIR__ . '/../../../src/services/approvals/AffiliateApprovalTarget.php'
        );
        $this->assertNotFalse($source, 'AffiliateApprovalTarget.php must be readable');

        // Regression: getRowLabel must have a branch for the missing-
        // affiliate case (otherwise the approvals queue crashes on an
        // orphaned approval row). Verify the structural invariant:
        // getRowLabel's body contains a null check and a fallback
        // label string.
        $start = strpos($source, 'function getRowLabel(');
        $this->assertNotFalse($start, 'AffiliateApprovalTarget must have a getRowLabel method');
        $end = strpos($source, 'function ', $start + 1);
        $body = substr($source, $start, $end === false ? null : $end - $start);

        $this->assertMatchesRegularExpression(
            '/if \(\s*\$\w+\s*===\s*null\s*\)/',
            $body,
            'getRowLabel must explicitly handle the missing-affiliate case.',
        );
        $this->assertStringContainsString(
            '(missing)',
            $body,
            'Missing-affiliate fallback label must include "(missing)" marker.',
        );
    }
}
