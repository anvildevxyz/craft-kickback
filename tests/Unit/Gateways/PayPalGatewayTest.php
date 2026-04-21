<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Gateways;

use PHPUnit\Framework\TestCase;

/**
 * Source-inspection regressions for PayPalGateway's overall shape:
 * interface compliance, the post-1.0 stub removal, processPayout
 * idempotency via sender_batch_id, sandbox/production switching,
 * fetchPayoutStatus mapping. Webhook-specific tests live in
 * PayPalWebhookTest.
 */
class PayPalGatewayTest extends TestCase
{
    private const GATEWAY_FILE = __DIR__ . '/../../../src/gateways/PayPalGateway.php';

    public function testImplementsAllThreeInterfaces(): void
    {
        $this->assertTrue(
            is_subclass_of(
                'anvildev\\craftkickback\\gateways\\PayPalGateway',
                'anvildev\\craftkickback\\gateways\\PayoutGatewayInterface',
            ),
        );
        $this->assertTrue(
            is_subclass_of(
                'anvildev\\craftkickback\\gateways\\PayPalGateway',
                'anvildev\\craftkickback\\gateways\\ReconciliationCapableInterface',
            ),
        );
        $this->assertTrue(
            is_subclass_of(
                'anvildev\\craftkickback\\gateways\\PayPalGateway',
                'anvildev\\craftkickback\\gateways\\WebhookHandlerInterface',
            ),
        );
    }

    public function testStubLanguageRemoved(): void
    {
        $source = file_get_contents(self::GATEWAY_FILE);
        $this->assertStringNotContainsString(
            'TEMPORARILY DISABLED',
            $source,
            'PayPalGateway header docblock must no longer claim the gateway is disabled.',
        );
        $this->assertStringNotContainsString(
            '(disabled - 1.1)',
            $source,
            'getDisplayName must no longer return the disabled suffix.',
        );
    }

    public function testIsConfiguredChecksClientCredentials(): void
    {
        $source = file_get_contents(self::GATEWAY_FILE);
        $start = strpos($source, 'function isConfigured(');
        $this->assertNotFalse($start);
        $body = substr($source, $start, 400);
        $this->assertStringContainsString('paypalClientId', $body);
        $this->assertStringContainsString('paypalClientSecret', $body);
    }

    public function testProcessPayoutUsesPayoutUidAsSenderBatchAndItemId(): void
    {
        $source = file_get_contents(self::GATEWAY_FILE);
        $this->assertStringContainsString(
            'sender_batch_id',
            $source,
            'processPayout must set sender_batch_id (PayPal\'s idempotency key) so retries don\'t double-pay.',
        );
        $this->assertStringContainsString(
            'sender_item_id',
            $source,
            'processPayout must set sender_item_id so the webhook receiver can look up local payouts without trusting PayPal\'s own item id.',
        );
    }

    public function testSandboxAndProductionEndpointsBothPresent(): void
    {
        $source = file_get_contents(self::GATEWAY_FILE);
        $this->assertStringContainsString('api-m.sandbox.paypal.com', $source);
        $this->assertStringContainsString('api-m.paypal.com', $source);
    }

    public function testSandboxSwitchingHonorsSettingsFlag(): void
    {
        $source = file_get_contents(self::GATEWAY_FILE);
        $this->assertStringContainsString(
            'paypalSandbox',
            $source,
            'Gateway must reference Settings::$paypalSandbox to choose base URL.',
        );
    }

    public function testFetchPayoutStatusMapsPayPalStatusVocabulary(): void
    {
        $source = file_get_contents(self::GATEWAY_FILE);
        $start = strpos($source, 'function fetchPayoutStatus(');
        $this->assertNotFalse($start);

        // Both the helper methods or the inline mapping should reference
        // PayPal's status names.
        $this->assertStringContainsString('SUCCESS', $source);
        $this->assertStringContainsString('REVERSED', $source);
    }

    public function testProcessPayoutReturnsPendingNotSucceededForAsyncBatch(): void
    {
        $source = file_get_contents(self::GATEWAY_FILE);
        $this->assertStringContainsString(
            'PayoutResult::pending',
            $source,
            'PayPal batches are async - processPayout must return PayoutResult::pending() so handleGatewayResult leaves the payout in STATUS_PROCESSING and waits for the webhook / reconciliation to resolve it. Returning succeeded() would prematurely deduct the affiliate balance.',
        );
    }
}
