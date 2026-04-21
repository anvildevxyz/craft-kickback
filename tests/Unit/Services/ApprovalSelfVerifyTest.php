<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Services;

use anvildev\craftkickback\exceptions\SelfVerificationException;
use anvildev\craftkickback\services\ApprovalService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ApprovalSelfVerifyTest extends TestCase
{
    #[Test]
    public function passesWhenCreatorIsNull(): void
    {
        ApprovalService::checkSelfVerify(resolverId: 42, creatorId: null);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function passesWhenResolverDiffersFromCreator(): void
    {
        ApprovalService::checkSelfVerify(resolverId: 42, creatorId: 7);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throwsWhenResolverEqualsCreator(): void
    {
        $this->expectException(SelfVerificationException::class);
        ApprovalService::checkSelfVerify(resolverId: 42, creatorId: 42);
    }

    #[Test]
    public function throwsEvenForAdminSelfVerification(): void
    {
        // Regression lock: "no admin bypass" - the rule is purely id-based,
        // with no branch on admin status.
        $adminId = 1;
        $this->expectException(SelfVerificationException::class);
        ApprovalService::checkSelfVerify(resolverId: $adminId, creatorId: $adminId);
    }

    #[Test]
    public function selfVerificationExceptionHasClearMessage(): void
    {
        try {
            ApprovalService::checkSelfVerify(resolverId: 5, creatorId: 5);
            $this->fail('Expected SelfVerificationException');
        } catch (SelfVerificationException $e) {
            $this->assertStringContainsString('cannot verify', strtolower($e->getMessage()));
        }
    }
}
