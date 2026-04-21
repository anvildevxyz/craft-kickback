<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Services;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TrackingServiceCookieTest extends TestCase
{
    #[Test]
    public function signCookieValueProducesHmacPrefixedString(): void
    {
        $data = ['code' => 'ABC123', 'clickId' => 42, 'timestamp' => 1700000000];
        $json = json_encode($data, JSON_THROW_ON_ERROR);
        $key = 'test-secret-key';

        $signed = TrackingServiceCookieTest::signForTest($json, $key);

        // Signed value should be longer than raw JSON (HMAC prefix added)
        $this->assertGreaterThan(strlen($json), strlen($signed));
        // Should not be valid JSON on its own (HMAC prepended)
        $this->assertNull(json_decode($signed, true));
    }

    #[Test]
    public function validateSignedCookieReturnsOriginalData(): void
    {
        $data = ['code' => 'ABC123', 'clickId' => 42, 'timestamp' => 1700000000];
        $json = json_encode($data, JSON_THROW_ON_ERROR);
        $key = 'test-secret-key';

        $signed = self::signForTest($json, $key);
        $validated = self::validateForTest($signed, $key);

        $this->assertSame($json, $validated);
    }

    #[Test]
    public function tamperedCookieFailsValidation(): void
    {
        $data = ['code' => 'ABC123', 'clickId' => 42, 'timestamp' => 1700000000];
        $json = json_encode($data, JSON_THROW_ON_ERROR);
        $key = 'test-secret-key';

        $signed = self::signForTest($json, $key);
        // Tamper with the payload
        $tampered = $signed . 'x';

        $this->assertFalse(self::validateForTest($tampered, $key));
    }

    #[Test]
    public function unsignedLegacyCookieFailsValidation(): void
    {
        $legacyJson = json_encode(['code' => 'OLD', 'clickId' => 1, 'timestamp' => 1600000000]);
        $key = 'test-secret-key';

        $this->assertFalse(self::validateForTest($legacyJson, $key));
    }

    /**
     * Mirrors the HMAC logic that will be used in TrackingService.
     * Uses hash_hmac + hash_equals - same algorithm as Craft's Security::hashData().
     */
    private static function signForTest(string $data, string $key): string
    {
        $hash = hash_hmac('sha256', $data, $key);
        return $hash . $data;
    }

    private static function validateForTest(string $data, string $key): string|false
    {
        $hashLength = 64; // SHA-256 hex length
        if (strlen($data) < $hashLength) {
            return false;
        }
        $hash = substr($data, 0, $hashLength);
        $payload = substr($data, $hashLength);
        $expected = hash_hmac('sha256', $payload, $key);

        if (!hash_equals($expected, $hash)) {
            return false;
        }

        return $payload;
    }
}
