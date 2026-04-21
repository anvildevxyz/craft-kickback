<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Gateways;

use anvildev\craftkickback\gateways\StripeGateway;
use PHPUnit\Framework\TestCase;

class StripeWebhookTest extends TestCase
{
    public function testMissingSignatureHeaderIsRejected(): void
    {
        $gateway = new StripeGateway();
        $result = $gateway->handleWebhook('{}', []);
        $this->assertFalse($result->verified);
        $this->assertNotNull($result->errorMessage);
    }

    public function testMissingWebhookSecretIsRejected(): void
    {
        // In a unit-test context KickBack::getInstance() returns null, so the secret is empty and
        // the method rejects before reaching signature verification.
        $gateway = new StripeGateway();
        $result = $gateway->handleWebhook(
            '{"type":"transfer.reversed","data":{"object":{"id":"tr_xxx"}}}',
            ['Stripe-Signature' => 't=1234567890,v1=deadbeef'],
        );
        $this->assertFalse($result->verified);
    }
}
