<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Services;

use anvildev\craftkickback\services\ApprovalService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RejectNoteValidationTest extends TestCase
{
    #[Test]
    public function returnsTrimmedStringForNormalInput(): void
    {
        $result = ApprovalService::requireNonEmptyRejectionNote('  looks wrong  ');
        $this->assertSame('looks wrong', $result);
    }

    #[Test]
    public function throwsOnNull(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ApprovalService::requireNonEmptyRejectionNote(null);
    }

    #[Test]
    public function throwsOnEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ApprovalService::requireNonEmptyRejectionNote('');
    }

    #[Test]
    public function throwsOnWhitespaceOnly(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ApprovalService::requireNonEmptyRejectionNote("   \t\n  ");
    }

    #[Test]
    public function preservesInternalWhitespace(): void
    {
        $result = ApprovalService::requireNonEmptyRejectionNote('amount does   not match invoice');
        $this->assertSame('amount does   not match invoice', $result);
    }
}
