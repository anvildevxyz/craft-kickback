<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Services;

use anvildev\craftkickback\exceptions\ApprovalAlreadyResolvedException;
use anvildev\craftkickback\records\ApprovalRecord;
use anvildev\craftkickback\services\ApprovalService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ApprovalResolvableTest extends TestCase
{
    #[Test]
    public function passesWhenStatusIsPending(): void
    {
        ApprovalService::checkResolvable(1, ApprovalRecord::STATUS_PENDING);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throwsWhenStatusIsApproved(): void
    {
        $this->expectException(ApprovalAlreadyResolvedException::class);
        ApprovalService::checkResolvable(1, ApprovalRecord::STATUS_APPROVED);
    }

    #[Test]
    public function throwsWhenStatusIsRejected(): void
    {
        $this->expectException(ApprovalAlreadyResolvedException::class);
        ApprovalService::checkResolvable(1, ApprovalRecord::STATUS_REJECTED);
    }

    #[Test]
    public function exceptionCarriesApprovalIdAndStatus(): void
    {
        try {
            ApprovalService::checkResolvable(42, ApprovalRecord::STATUS_APPROVED);
            $this->fail('Expected ApprovalAlreadyResolvedException');
        } catch (ApprovalAlreadyResolvedException $e) {
            $this->assertStringContainsString('#42', $e->getMessage());
            $this->assertStringContainsString(ApprovalRecord::STATUS_APPROVED, $e->getMessage());
        }
    }
}
