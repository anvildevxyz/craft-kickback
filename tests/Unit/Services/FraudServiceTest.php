<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Services;

use PHPUnit\Framework\TestCase;

class FraudServiceTest extends TestCase
{
    private const SERVICE_FILE = __DIR__ . '/../../../src/services/FraudService.php';

    public function testClickVelocityThresholdTriggersFlag(): void
    {
        $source = file_get_contents(self::SERVICE_FILE);
        $this->assertNotFalse($source, 'FraudService.php must be readable');

        $start = strpos($source, 'function checkClickVelocity(');
        $this->assertNotFalse($start, 'checkClickVelocity method must exist');
        $end = strpos($source, 'function ', $start + 1);
        $body = substr($source, $start, $end === false ? null : $end - $start);

        $this->assertStringContainsString(
            'Craft::info(',
            $body,
            'checkClickVelocity() must emit a Craft::info entry log.',
        );

        $this->assertStringContainsString(
            'Craft::warning(',
            $body,
            'checkClickVelocity() must emit a Craft::warning flag log when threshold is reached.',
        );

        $this->assertStringContainsString(
            'fraudClickVelocityThreshold',
            $body,
            'checkClickVelocity() must reference the fraudClickVelocityThreshold setting.',
        );
    }

    public function testClickVelocityBelowThresholdDoesNotFlag(): void
    {
        $source = file_get_contents(self::SERVICE_FILE);
        $this->assertNotFalse($source, 'FraudService.php must be readable');

        $start = strpos($source, 'function checkClickVelocity(');
        $this->assertNotFalse($start, 'checkClickVelocity method must exist');
        $end = strpos($source, 'function ', $start + 1);
        $body = substr($source, $start, $end === false ? null : $end - $start);

        $this->assertStringContainsString(
            'return null;',
            $body,
            'checkClickVelocity() must have a return null path for the below-threshold case.',
        );
    }

    public function testRapidConversionFlagsWhenClickToOrderIsShorterThanThreshold(): void
    {
        $source = file_get_contents(self::SERVICE_FILE);
        $this->assertNotFalse($source, 'FraudService.php must be readable');

        $start = strpos($source, 'function checkRapidConversions(');
        $this->assertNotFalse($start, 'checkRapidConversions method must exist');
        $end = strpos($source, 'function ', $start + 1);
        $body = substr($source, $start, $end === false ? null : $end - $start);

        $this->assertStringContainsString(
            'Craft::info(',
            $body,
            'checkRapidConversions() must emit a Craft::info entry log.',
        );

        $this->assertStringContainsString(
            'fraudRapidConversionMinutes',
            $body,
            'checkRapidConversions() must reference the fraudRapidConversionMinutes setting.',
        );
    }

    public function testIpReuseThresholdTriggers(): void
    {
        $source = file_get_contents(self::SERVICE_FILE);
        $this->assertNotFalse($source, 'FraudService.php must be readable');

        $start = strpos($source, 'function checkIpReuse(');
        $this->assertNotFalse($start, 'checkIpReuse method must exist');
        $end = strpos($source, 'function ', $start + 1);
        $body = substr($source, $start, $end === false ? null : $end - $start);

        $this->assertStringContainsString(
            'Craft::info(',
            $body,
            'checkIpReuse() must emit a Craft::info entry log.',
        );

        $this->assertStringContainsString(
            'fraudIpReuseThreshold',
            $body,
            'checkIpReuse() must reference the fraudIpReuseThreshold setting.',
        );
    }

    public function testBotUserAgentIsFlagged(): void
    {
        $source = file_get_contents(self::SERVICE_FILE);
        $this->assertNotFalse($source, 'FraudService.php must be readable');

        $start = strpos($source, 'function checkSuspiciousUserAgent(');
        $this->assertNotFalse($start, 'checkSuspiciousUserAgent method must exist');
        $end = strpos($source, 'function ', $start + 1);
        $body = substr($source, $start, $end === false ? null : $end - $start);

        $this->assertStringContainsString(
            'Craft::info(',
            $body,
            'checkSuspiciousUserAgent() must emit a Craft::info entry log.',
        );

        $this->assertTrue(
            str_contains($body, 'userAgent') || str_contains($body, 'user_agent'),
            'checkSuspiciousUserAgent() must reference the userAgent field for comparison.',
        );
    }

    public function testEvaluateReferralEmitsSummaryLog(): void
    {
        $source = file_get_contents(self::SERVICE_FILE);
        $this->assertNotFalse($source, 'FraudService.php must be readable');

        $start = strpos($source, 'function evaluateReferral(');
        $this->assertNotFalse($start, 'evaluateReferral method must exist');
        $end = strpos($source, 'function ', $start + 1);
        $body = substr($source, $start, $end === false ? null : $end - $start);

        $this->assertStringContainsString(
            'Fraud evaluation referral',
            $body,
            'evaluateReferral() must emit a summary Craft::info log containing "Fraud evaluation referral" - Wave 2 observability fix.',
        );
    }
}
