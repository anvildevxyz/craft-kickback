<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Services;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ReferralCodeValidationTest extends TestCase
{
    /**
     * Mirrors the validation logic that will be added to KickBack::handleSiteReferralParam().
     */
    private static function isValidReferralCode(string $code): bool
    {
        return strlen($code) <= 64 && preg_match('/^[a-zA-Z0-9_-]+$/', $code) === 1;
    }

    #[Test]
    #[DataProvider('validCodesProvider')]
    public function acceptsValidCodes(string $code): void
    {
        $this->assertTrue(self::isValidReferralCode($code));
    }

    public static function validCodesProvider(): array
    {
        return [
            'simple alpha' => ['abc123'],
            'with hyphens' => ['my-code'],
            'with underscores' => ['my_code'],
            'mixed' => ['Affiliate-Code_42'],
            'single char' => ['x'],
            'max length (64)' => [str_repeat('a', 64)],
        ];
    }

    #[Test]
    #[DataProvider('invalidCodesProvider')]
    public function rejectsInvalidCodes(string $code): void
    {
        $this->assertFalse(self::isValidReferralCode($code));
    }

    public static function invalidCodesProvider(): array
    {
        return [
            'too long (65)' => [str_repeat('a', 65)],
            'contains spaces' => ['has space'],
            'contains SQL chars' => ["'; DROP TABLE--"],
            'contains angle brackets' => ['<script>'],
            'contains dots' => ['code.with.dots'],
            'empty string' => [''],
            'unicode' => ["\xc3\xa9"],
        ];
    }
}
