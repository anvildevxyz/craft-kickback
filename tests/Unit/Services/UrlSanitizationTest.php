<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Services;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class UrlSanitizationTest extends TestCase
{
    /**
     * Mirrors the sanitization logic that will be added to KickBack::handleSiteReferralParam().
     */
    private static function sanitizeLandingUrl(string $url, string $fallbackPath): string
    {
        if (strlen($url) > 2048) {
            return $fallbackPath;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return $fallbackPath;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return $fallbackPath;
        }

        return $url;
    }

    #[Test]
    #[DataProvider('validUrlsProvider')]
    public function acceptsValidUrls(string $url): void
    {
        $this->assertSame($url, self::sanitizeLandingUrl($url, '/fallback'));
    }

    public static function validUrlsProvider(): array
    {
        return [
            'simple https' => ['https://example.com/page'],
            'with query params' => ['https://example.com/page?ref=abc&utm=test'],
            'http allowed' => ['http://example.com/shop'],
            'with port' => ['https://example.com:8443/page'],
            'with path segments' => ['https://example.com/a/b/c/d'],
            'with fragment' => ['https://example.com/page#section'],
        ];
    }

    #[Test]
    #[DataProvider('dangerousUrlsProvider')]
    public function rejectsDangerousUrls(string $url): void
    {
        $this->assertSame('/fallback', self::sanitizeLandingUrl($url, '/fallback'));
    }

    public static function dangerousUrlsProvider(): array
    {
        return [
            'javascript scheme' => ['javascript:alert(1)'],
            'data scheme' => ['data:text/html,<script>alert(1)</script>'],
            'ftp scheme' => ['ftp://evil.com/payload'],
            'too long' => ['https://example.com/' . str_repeat('a', 2048)],
            'not a URL' => ['not-a-url-at-all'],
            'empty scheme' => ['://example.com'],
        ];
    }
}
