<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Gateways;

use PHPUnit\Framework\TestCase;

/**
 * Source-inspection regressions for PayPalGateway::handleWebhook.
 * The runtime path requires a Craft bootstrap + a sandbox PayPal
 * webhook id + signed test payloads, none of which are in this
 * unit-test layer. These tests lock in the structural invariants
 * that protect against regressions: signature verification round-
 * trip, paypalWebhookId requirement, sender_item_id lookup
 * discipline, and event-type dispatch.
 */
class PayPalWebhookTest extends TestCase
{
    private const GATEWAY_FILE = __DIR__ . '/../../../src/gateways/PayPalGateway.php';

    public function testGatewayImplementsWebhookHandlerInterface(): void
    {
        $this->assertTrue(
            is_subclass_of(
                'anvildev\\craftkickback\\gateways\\PayPalGateway',
                'anvildev\\craftkickback\\gateways\\WebhookHandlerInterface',
            ),
        );
    }

    public function testHandleWebhookExists(): void
    {
        $body = $this->extractMethodBody('handleWebhook');
        $this->assertNotSame('', $body, 'handleWebhook method must exist');
    }

    public function testHandleWebhookRequiresAllFiveTransmissionHeaders(): void
    {
        $body = $this->extractMethodBody('handleWebhook');
        foreach ([
            'PAYPAL-AUTH-ALGO',
            'PAYPAL-CERT-URL',
            'PAYPAL-TRANSMISSION-ID',
            'PAYPAL-TRANSMISSION-SIG',
            'PAYPAL-TRANSMISSION-TIME',
        ] as $header) {
            $this->assertStringContainsString(
                $header,
                $body,
                "handleWebhook must check for the {$header} transmission header.",
            );
        }
    }

    public function testHandleWebhookCallsVerifySignatureEndpoint(): void
    {
        $body = $this->extractMethodBody('handleWebhook');
        $this->assertStringContainsString(
            '/v1/notifications/verify-webhook-signature',
            $body,
            'handleWebhook must round-trip to PayPal\'s verify-webhook-signature endpoint instead of attempting local HMAC.',
        );
        $this->assertStringContainsString(
            'webhook_id',
            $body,
            'Verification request must include the configured webhook_id.',
        );
    }

    public function testHandleWebhookReadsPaypalWebhookIdSetting(): void
    {
        $body = $this->extractMethodBody('handleWebhook');
        $this->assertStringContainsString(
            'paypalWebhookId',
            $body,
            'handleWebhook must read the paypalWebhookId setting (added in Task 1).',
        );
        $this->assertStringContainsString(
            'App::parseEnv',
            $body,
            'paypalWebhookId must be read via App::parseEnv so it can come from .env.',
        );
    }

    public function testHandleWebhookLooksUpPayoutBySenderItemId(): void
    {
        $body = $this->extractMethodBody('handleWebhook');
        $this->assertStringContainsString(
            'sender_item_id',
            $body,
            'handleWebhook must look up the local payout via sender_item_id (the local uid we stamped at batch-create time), never via PayPal\'s payout_item_id which is attacker-controlled relative to our records.',
        );
        $this->assertStringContainsString(
            'findByGatewayReference',
            $body,
            'Payout lookup must go through PayoutService::findByGatewayReference.',
        );
    }

    public function testHandleWebhookDispatchesAllExpectedEventTypes(): void
    {
        $body = $this->extractMethodBody('handleWebhook');
        foreach ([
            'PAYMENT.PAYOUTS-ITEM.SUCCEEDED',
            'PAYMENT.PAYOUTS-ITEM.FAILED',
            'PAYMENT.PAYOUTS-ITEM.RETURNED',
            'PAYMENT.PAYOUTS-ITEM.UNCLAIMED',
            'PAYMENT.PAYOUTS-ITEM.BLOCKED',
            'PAYMENT.PAYOUTS-ITEM.DENIED',
        ] as $eventType) {
            $this->assertStringContainsString(
                $eventType,
                $body,
                "handleWebhook must dispatch on {$eventType}.",
            );
        }
    }

    public function testHandleWebhookRoutesSucceededToCompletePayoutAndFailureToFailPayout(): void
    {
        $body = $this->extractMethodBody('handleWebhook');
        $this->assertStringContainsString('completePayout', $body);
        $this->assertStringContainsString('failPayout', $body);
    }

    public function testHandleWebhookSanitizesNetworkErrorBeforeLogging(): void
    {
        $body = $this->extractMethodBody('handleWebhook');
        $this->assertStringContainsString(
            'str_replace(["\r", "\n"]',
            $body,
            'Verification API call failure path must sanitize newlines before logging to prevent log injection (matches the WebhooksController fix from Task 1.4 follow-up).',
        );
    }

    private function extractMethodBody(string $name): string
    {
        $source = file_get_contents(self::GATEWAY_FILE);
        $this->assertNotFalse($source, self::GATEWAY_FILE . ' must be readable');
        $start = strpos($source, "function {$name}(");
        if ($start === false) {
            return '';
        }
        $next = strpos($source, "\n    public function ", $start + 1);
        if ($next === false) {
            $next = strpos($source, "\n    private function ", $start + 1);
        }
        return substr($source, $start, $next === false ? null : $next - $start);
    }
}
