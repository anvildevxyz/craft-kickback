<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Elements;

use anvildev\craftkickback\elements\AffiliateElement;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AffiliateElementParentValidationTest extends TestCase
{
    // ── Cycle detection ─────────────────────────────────────────

    #[Test]
    public function selfParentDetectedAsCycle(): void
    {
        $this->assertTrue(
            AffiliateElement::detectsCycle(1, 1, static fn(int $id): ?int => null, 5),
        );
    }

    #[Test]
    public function directCycleDetected(): void
    {
        // A(1) -> B(2) -> A(1): setting A's parent to B creates A->B->A
        $parents = [2 => 1]; // affiliate 2 has parentAffiliateId 1

        $this->assertTrue(
            AffiliateElement::detectsCycle(1, 2, static fn(int $id): ?int => $parents[$id] ?? null, 5),
        );
    }

    #[Test]
    public function indirectCycleDetected(): void
    {
        // A(1) -> B(2) -> C(3) -> A(1)
        $parents = [3 => 2, 2 => 1];

        $this->assertTrue(
            AffiliateElement::detectsCycle(1, 3, static fn(int $id): ?int => $parents[$id] ?? null, 10),
        );
    }

    #[Test]
    public function validChainAccepted(): void
    {
        // A(1) -> B(2) -> C(3) -> null
        $parents = [2 => 3, 3 => null];

        $this->assertFalse(
            AffiliateElement::detectsCycle(1, 2, static fn(int $id): ?int => $parents[$id] ?? null, 10),
        );
    }

    #[Test]
    public function chainBoundedByMaxDepth(): void
    {
        // Long chain: 5 -> 4 -> 3 -> 2 -> 1 -> null
        // Setting affiliate 99's parent to 5 with maxDepth=2 should NOT walk far enough to find 99
        $parents = [5 => 4, 4 => 3, 3 => 2, 2 => 1, 1 => null];

        $this->assertFalse(
            AffiliateElement::detectsCycle(99, 5, static fn(int $id): ?int => $parents[$id] ?? null, 2),
        );
    }

    #[Test]
    public function cycleDetectedWithinMaxDepth(): void
    {
        // A(1) -> B(2) -> A(1): direct cycle, maxDepth=1 is enough
        $parents = [2 => 1];

        $this->assertTrue(
            AffiliateElement::detectsCycle(1, 2, static fn(int $id): ?int => $parents[$id] ?? null, 1),
        );
    }

    #[Test]
    public function noCycleWithDisconnectedChain(): void
    {
        // A(1) parent set to D(4); D -> E(5) -> null. No link back to A.
        $parents = [4 => 5, 5 => null];

        $this->assertFalse(
            AffiliateElement::detectsCycle(1, 4, static fn(int $id): ?int => $parents[$id] ?? null, 10),
        );
    }

    // ── Cross-program validation ────────────────────────────────

    #[Test]
    public function sameProgramIsNotMismatch(): void
    {
        $this->assertFalse(AffiliateElement::parentProgramMismatch(1, 1));
    }

    #[Test]
    public function differentProgramIsMismatch(): void
    {
        $this->assertTrue(AffiliateElement::parentProgramMismatch(1, 2));
    }

    #[Test]
    public function nullParentProgramSkipsMismatch(): void
    {
        $this->assertFalse(AffiliateElement::parentProgramMismatch(null, 1));
    }

    #[Test]
    public function nullChildProgramSkipsMismatch(): void
    {
        $this->assertFalse(AffiliateElement::parentProgramMismatch(1, null));
    }

    // ── Parent status resolution ────────────────────────────────

    #[Test]
    public function activeParentIsValid(): void
    {
        $this->assertSame('valid', AffiliateElement::resolveParentStatus('active'));
    }

    #[Test]
    public function pendingParentIsInactive(): void
    {
        $this->assertSame('inactive', AffiliateElement::resolveParentStatus('pending'));
    }

    #[Test]
    public function suspendedParentIsInactive(): void
    {
        $this->assertSame('inactive', AffiliateElement::resolveParentStatus('suspended'));
    }

    #[Test]
    public function rejectedParentIsInactive(): void
    {
        $this->assertSame('inactive', AffiliateElement::resolveParentStatus('rejected'));
    }
}
