<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Gateways;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Stripe\Account;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Transfer;
use Stripe\Webhook;

/**
 * The Stripe SDK surface {@see \anvildev\craftkickback\gateways\StripeGateway} stands on.
 *
 * composer.json accepts stripe/stripe-php v13 through v21, so Kickback can be
 * installed next to plugins that cap the same SDK lower — Solspace Freeform at
 * ^15, Formie at ^16, Craft Commerce's Stripe gateway at ^13. The lock pins one
 * version, so nothing else in the suite would notice the floor breaking.
 *
 * StripeWebhookTest covers the guard clauses in front of the SDK and stops
 * before reaching it. These assertions are the range's promise: they exercise
 * the SDK itself and reach no network — the client is built but never called,
 * and the webhook payload is signed here.
 */
class StripeSdkSurfaceTest extends TestCase
{
    private const SECRET = 'whsec_test_surface';

    #[Test]
    public function clientTakesABareApiKey(): void
    {
        // StripeGateway::getClient() passes the secret key as a string, not a config array.
        $this->assertInstanceOf(StripeClient::class, new StripeClient('sk_test_surface'));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function serviceMethods(): array
    {
        return [
            'processPayout' => ['transfers', 'create'],
            'fetchPayoutStatus' => ['transfers', 'retrieve'],
            'createConnectedAccount' => ['accounts', 'create'],
            'isAccountReady' => ['accounts', 'retrieve'],
            'createOnboardingLink' => ['accountLinks', 'create'],
        ];
    }

    #[Test]
    #[DataProvider('serviceMethods')]
    public function clientExposesTheServicesTheGatewayCalls(string $service, string $method): void
    {
        $client = new StripeClient('sk_test_surface');

        $this->assertTrue(
            method_exists($client->$service, $method),
            "stripe-php no longer exposes {$service}->{$method}()",
        );
    }

    /**
     * processPayout() passes an options array second (the idempotency key), so a
     * one-argument signature would break it without failing the check above.
     */
    #[Test]
    public function transferCreateTakesAnOptionsArgument(): void
    {
        $client = new StripeClient('sk_test_surface');
        $reflection = new \ReflectionMethod($client->transfers, 'create');

        $this->assertGreaterThanOrEqual(2, $reflection->getNumberOfParameters());
    }

    #[Test]
    public function constructEventVerifiesASignedPayload(): void
    {
        $payload = self::payload();
        $event = Webhook::constructEvent($payload, self::signatureFor($payload), self::SECRET);

        // Every property handleWebhook() reads off the event.
        $this->assertSame('transfer.reversed', $event->type);

        $transfer = $event->data->object;
        $this->assertIsObject($transfer);
        $this->assertSame('tr_surface', $transfer->id);
        $this->assertSame('42', $transfer->metadata->kickback_payout_id);
    }

    #[Test]
    public function constructEventRejectsATamperedSignature(): void
    {
        $this->expectException(SignatureVerificationException::class);
        Webhook::constructEvent(self::payload(), self::signatureFor('{"tampered":true}'), self::SECRET);
    }

    #[Test]
    public function transferExposesTheReversalFieldsReconciliationReads(): void
    {
        $transfer = Transfer::constructFrom([
            'id' => 'tr_surface',
            'amount' => 5000,
            'amount_reversed' => 5000,
            'reversed' => true,
        ]);

        $this->assertTrue($transfer->reversed);
        $this->assertSame(5000, $transfer->amount_reversed);
        $this->assertSame(5000, $transfer->amount);
    }

    #[Test]
    public function accountExposesTheReadinessFlags(): void
    {
        $account = Account::constructFrom([
            'id' => 'acct_surface',
            'charges_enabled' => true,
            'payouts_enabled' => false,
        ]);

        $this->assertSame('acct_surface', $account->id);
        $this->assertTrue($account->charges_enabled);
        $this->assertFalse($account->payouts_enabled);
    }

    private static function payload(): string
    {
        return json_encode([
            'id' => 'evt_surface',
            'object' => 'event',
            'type' => 'transfer.reversed',
            'data' => [
                'object' => [
                    'id' => 'tr_surface',
                    'object' => 'transfer',
                    'amount' => 5000,
                    'amount_reversed' => 5000,
                    'reversed' => true,
                    'metadata' => ['kickback_payout_id' => '42'],
                ],
            ],
        ], JSON_THROW_ON_ERROR);
    }

    /** A `Stripe-Signature` header for the payload, built the way Stripe builds it. */
    private static function signatureFor(string $payload): string
    {
        $timestamp = time();
        $hash = hash_hmac('sha256', "{$timestamp}.{$payload}", self::SECRET);

        return "t={$timestamp},v1={$hash}";
    }
}
