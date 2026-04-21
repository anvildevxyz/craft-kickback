<?php

declare(strict_types=1);

namespace anvildev\craftkickback\gateways;

use anvildev\craftkickback\elements\AffiliateElement;
use anvildev\craftkickback\elements\PayoutElement;
use anvildev\craftkickback\KickBack;
use Craft;
use craft\helpers\App;
use GuzzleHttp\Exception\RequestException;

class PayPalGateway implements PayoutGatewayInterface, ReconciliationCapableInterface, WebhookHandlerInterface
{
    private const SANDBOX_BASE_URL = 'https://api-m.sandbox.paypal.com';
    private const PRODUCTION_BASE_URL = 'https://api-m.paypal.com';

    private ?\GuzzleHttp\Client $httpClient = null;
    private ?string $accessToken = null;
    private int $tokenExpiresAt = 0;

    public function getHandle(): string
    {
        return 'paypal';
    }

    public function getDisplayName(): string
    {
        return 'PayPal';
    }

    public function isConfigured(): bool
    {
        $settings = KickBack::getInstance()->getSettings();

        return !empty(App::parseEnv($settings->paypalClientId))
            && !empty(App::parseEnv($settings->paypalClientSecret));
    }

    public function isAffiliateReady(AffiliateElement $affiliate): bool
    {
        return !empty($affiliate->paypalEmail);
    }

    public function processPayout(PayoutElement $payout, AffiliateElement $affiliate): PayoutResult
    {
        if (!$this->isConfigured()) {
            return PayoutResult::failed('PayPal gateway is not configured.');
        }

        if (empty($affiliate->paypalEmail)) {
            return PayoutResult::failed('Affiliate has no PayPal email address configured.');
        }

        $siteName = Craft::$app->getSites()->getCurrentSite()->getName();

        $payload = [
            'sender_batch_header' => [
                'sender_batch_id' => $payout->uid,
                'email_subject' => "You have a payout from {$siteName}",
            ],
            'items' => [
                [
                    'recipient_type' => 'EMAIL',
                    'receiver' => $affiliate->paypalEmail,
                    'amount' => [
                        'value' => number_format((float)$payout->amount, 2, '.', ''),
                        'currency' => $payout->currency,
                    ],
                    'sender_item_id' => $payout->uid,
                    'note' => "Affiliate payout #{$payout->id}",
                ],
            ],
        ];

        try {
            $response = $this->getHttpClient()->post('/v1/payments/payouts', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->getAccessToken(),
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $body = json_decode((string)$response->getBody(), true) ?? [];
            $batchId = $body['batch_header']['payout_batch_id'] ?? '';

            if (empty($batchId)) {
                return PayoutResult::failed('PayPal returned a success response but with no payout_batch_id.');
            }

            $payout->gatewayBatchId = $batchId;
            Craft::info("PayPal payout batch created: {$batchId} for payout #{$payout->id}", __METHOD__);

            return PayoutResult::pending($batchId);
        } catch (RequestException $e) {
            Craft::error("PayPal payout failed for payout #{$payout->id}: " . $e->getMessage(), __METHOD__);
            return PayoutResult::failed($this->sanitizeGuzzleError($e, $payout->id));
        }
    }

    /**
     * @param array<array{payout: PayoutElement, affiliate: AffiliateElement}> $items
     * @return PayoutResult[]
     */
    public function processBatch(array $items): array
    {
        if (!$this->isConfigured()) {
            return array_map(fn() => PayoutResult::failed('PayPal gateway is not configured.'), $items);
        }

        $validItems = [];
        $results = [];

        foreach ($items as $index => $item) {
            if (empty($item['affiliate']->paypalEmail)) {
                $results[$index] = PayoutResult::failed('Affiliate has no PayPal email address configured.');
            } else {
                $validItems[$index] = $item;
            }
        }

        if (empty($validItems)) {
            return array_values($results);
        }

        $siteName = Craft::$app->getSites()->getCurrentSite()->getName();
        $senderBatchId = reset($validItems)['payout']->uid . '_batch';

        $batchItems = [];
        foreach ($validItems as $item) {
            $batchItems[] = [
                'recipient_type' => 'EMAIL',
                'receiver' => $item['affiliate']->paypalEmail,
                'amount' => [
                    'value' => number_format((float)$item['payout']->amount, 2, '.', ''),
                    'currency' => $item['payout']->currency,
                ],
                'sender_item_id' => $item['payout']->uid,
                'note' => "Affiliate payout #{$item['payout']->id}",
            ];
        }

        try {
            $response = $this->getHttpClient()->post('/v1/payments/payouts', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->getAccessToken(),
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'sender_batch_header' => [
                        'sender_batch_id' => $senderBatchId,
                        'email_subject' => "You have a payout from {$siteName}",
                    ],
                    'items' => $batchItems,
                ],
            ]);

            $body = json_decode((string)$response->getBody(), true) ?? [];
            $batchId = $body['batch_header']['payout_batch_id'] ?? '';

            if (empty($batchId)) {
                $result = PayoutResult::failed('PayPal returned a success response but with no payout_batch_id.');
            } else {
                Craft::info(sprintf('PayPal batch created: %s (%d items)', $batchId, count($validItems)), __METHOD__);
                $result = PayoutResult::pending($batchId);
                foreach ($validItems as $item) {
                    $item['payout']->gatewayBatchId = $batchId;
                }
            }
        } catch (RequestException $e) {
            Craft::error('PayPal batch payout failed: ' . $e->getMessage(), __METHOD__);
            $result = PayoutResult::failed($this->sanitizeGuzzleError($e, null));
        }

        foreach (array_keys($validItems) as $index) {
            $results[$index] = $result;
        }
        ksort($results);
        return array_values($results);
    }

    private function getBaseUrl(): string
    {
        return KickBack::getInstance()->getSettings()->paypalSandbox
            ? self::SANDBOX_BASE_URL
            : self::PRODUCTION_BASE_URL;
    }

    private function getHttpClient(): \GuzzleHttp\Client
    {
        return $this->httpClient ??= Craft::createGuzzleClient([
            'base_uri' => $this->getBaseUrl(),
            'timeout' => 30,
            'connect_timeout' => 10,
        ]);
    }

    /**
     * Cached in-memory for 9 minutes -- long enough for batch submission,
     * short enough that long-running workers see fresh tokens.
     */
    private function getAccessToken(): string
    {
        if ($this->accessToken !== null && time() < $this->tokenExpiresAt) {
            return $this->accessToken;
        }

        $settings = KickBack::getInstance()->getSettings();
        $response = Craft::createGuzzleClient([
            'base_uri' => $this->getBaseUrl(),
            'timeout' => 15,
            'connect_timeout' => 10,
        ])->post('/v1/oauth2/token', [
            'auth' => [App::parseEnv($settings->paypalClientId), App::parseEnv($settings->paypalClientSecret)],
            'form_params' => ['grant_type' => 'client_credentials'],
        ]);

        $token = (json_decode((string)$response->getBody(), true) ?? [])['access_token'] ?? '';
        if (empty($token)) {
            throw new \RuntimeException('PayPal OAuth token response did not contain access_token.');
        }

        $this->accessToken = $token;
        $this->tokenExpiresAt = time() + 540;

        return $token;
    }

    public function fetchPayoutStatus(PayoutElement $payout): string
    {
        if ($payout->transactionId === null || !$this->isConfigured()) {
            return 'unknown';
        }

        try {
            $response = $this->getHttpClient()->get('/v1/payments/payouts/' . urlencode($payout->transactionId), [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->getAccessToken(),
                    'Accept' => 'application/json',
                ],
            ]);

            $body = json_decode((string)$response->getBody(), true);
            if (!is_array($body)) {
                return 'unknown';
            }

            // Prefer per-item status (precise) over batch rollup (coarse)
            $itemStatus = $body['items'][0]['transaction_status'] ?? null;
            if ($itemStatus !== null) {
                return $this->mapPayPalItemStatus((string)$itemStatus);
            }

            $batchStatus = $body['batch_header']['batch_status'] ?? null;
            return $batchStatus !== null ? $this->mapPayPalBatchStatus((string)$batchStatus) : 'unknown';
        } catch (\Throwable $e) {
            Craft::warning("PayPal reconciliation fetch failed for payout #{$payout->id}: {$e->getMessage()}", __METHOD__);
            return 'unknown';
        }
    }

    /**
     * REVERSED/REFUNDED both map to 'reversed' -- semantically distinct on
     * PayPal's side but both warrant the same restore-balance action here.
     */
    private function mapPayPalItemStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'SUCCESS' => 'completed',
            'REVERSED', 'REFUNDED' => 'reversed',
            default => 'unknown',
        };
    }

    private function mapPayPalBatchStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'SUCCESS' => 'completed',
            'CANCELED' => 'reversed',
            default => 'unknown',
        };
    }

    /**
     * @param array<string, string> $headers
     */
    public function handleWebhook(string $rawBody, array $headers): WebhookResult
    {
        $required = [
            'PAYPAL-AUTH-ALGO',
            'PAYPAL-CERT-URL',
            'PAYPAL-TRANSMISSION-ID',
            'PAYPAL-TRANSMISSION-SIG',
            'PAYPAL-TRANSMISSION-TIME',
        ];
        $verification = [];
        foreach ($required as $key) {
            $value = $headers[$key] ?? $headers[strtolower($key)] ?? $headers[ucwords(strtolower($key), '-')] ?? null;
            if ($value === null || $value === '') {
                return WebhookResult::unverified("Missing required PayPal header: {$key}");
            }
            $verification[$key] = $value;
        }

        try {
            $plugin = KickBack::getInstance();
        } catch (\Error) {
            $plugin = null;
        }
        if ($plugin === null) {
            return WebhookResult::unverified('Plugin not bootstrapped.');
        }

        $settings = $plugin->getSettings();
        $webhookId = App::parseEnv($settings->paypalWebhookId);
        if ($webhookId === '') {
            return WebhookResult::unverified('PayPal webhook id is not configured.');
        }

        if (!$this->isConfigured()) {
            return WebhookResult::unverified('PayPal gateway is not configured.');
        }

        $event = json_decode($rawBody, true);
        if (!is_array($event)) {
            return WebhookResult::unverified('Webhook body is not valid JSON.');
        }

        try {
            $response = $this->getHttpClient()->post('/v1/notifications/verify-webhook-signature', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->getAccessToken(),
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => [
                    'auth_algo' => $verification['PAYPAL-AUTH-ALGO'],
                    'cert_url' => $verification['PAYPAL-CERT-URL'],
                    'transmission_id' => $verification['PAYPAL-TRANSMISSION-ID'],
                    'transmission_sig' => $verification['PAYPAL-TRANSMISSION-SIG'],
                    'transmission_time' => $verification['PAYPAL-TRANSMISSION-TIME'],
                    'webhook_id' => $webhookId,
                    'webhook_event' => $event,
                ],
            ]);

            $verificationBody = json_decode((string)$response->getBody(), true);
            $status = $verificationBody['verification_status'] ?? null;

            if ($status !== 'SUCCESS') {
                Craft::warning(
                    'PayPal webhook verification returned ' . ($status ?? 'unknown') . ' for transmission ' . $verification['PAYPAL-TRANSMISSION-ID'],
                    __METHOD__,
                );
                return WebhookResult::unverified('Signature verification failed.');
            }
        } catch (\Throwable $e) {
            // Treat as unverified so PayPal retries; reconciliation catches permanently-dropped events
            Craft::warning('PayPal webhook verification API call failed: ' . str_replace(["\r", "\n"], ' ', $e->getMessage()), __METHOD__);
            return WebhookResult::unverified('Verification API call failed.');
        }

        $resource = $event['resource'] ?? null;
        if (!is_array($resource)) {
            return WebhookResult::verified(processed: false);
        }

        // sender_item_id lives in flat format or nested payout_item wrapper
        $senderItemId = $resource['sender_item_id']
            ?? $resource['payout_item']['sender_item_id']
            ?? null;

        if ($senderItemId === null || !is_string($senderItemId)) {
            Craft::info(
                'PayPal webhook missing sender_item_id, ignoring (transmission ' . $verification['PAYPAL-TRANSMISSION-ID'] . ')',
                __METHOD__,
            );
            return WebhookResult::verified(processed: false);
        }

        $payout = $plugin->payouts->findByGatewayReference($senderItemId);
        if ($payout === null) {
            Craft::info(
                "PayPal webhook for unknown sender_item_id={$senderItemId}; not our payout",
                __METHOD__,
            );
            return WebhookResult::verified(processed: false);
        }

        $eventType = $event['event_type'] ?? '';

        match ($eventType) {
            'PAYMENT.PAYOUTS-ITEM.SUCCEEDED' => $plugin->payouts->completePayout($payout, $senderItemId),
            'PAYMENT.PAYOUTS-ITEM.FAILED',
            'PAYMENT.PAYOUTS-ITEM.RETURNED',
            'PAYMENT.PAYOUTS-ITEM.UNCLAIMED',
            'PAYMENT.PAYOUTS-ITEM.BLOCKED',
            'PAYMENT.PAYOUTS-ITEM.DENIED' => $plugin->payouts->failPayout($payout, "PayPal: {$eventType}"),
            default => Craft::info(
                "Unhandled PayPal event type: {$eventType} for payout #{$payout->id}",
                __METHOD__,
            ),
        };

        return WebhookResult::verified(processed: true, payoutId: (string)$payout->id);
    }

    private function sanitizeGuzzleError(RequestException $e, ?int $payoutId): string
    {
        $response = $e->getResponse();
        if ($response === null) {
            return 'PayPal API request failed (network error).';
        }

        $status = $response->getStatusCode();

        try {
            $body = json_decode((string)$response->getBody(), true) ?? [];
            if (!empty($body['name'])) {
                return "PayPal API error [{$status}]: {$body['name']}" . (!empty($body['message']) ? " - {$body['message']}" : '');
            }
        } catch (\Throwable) {
        }

        return "PayPal API error: HTTP {$status}.";
    }
}
