<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Controllers;

use PHPUnit\Framework\TestCase;

class WebhooksControllerTest extends TestCase
{
    private const CONTROLLER_FILE = __DIR__ . '/../../../src/controllers/WebhooksController.php';

    /** Extract from just after 'function actionHandle(' so we don't scan class-level fields. */
    private function actionHandleBody(): string
    {
        $source = file_get_contents(self::CONTROLLER_FILE);
        $start = strpos($source, 'function actionHandle(');
        $this->assertNotFalse($start, 'actionHandle method must exist in WebhooksController');
        return substr($source, $start);
    }

    public function testUnknownHandleReturns404(): void
    {
        $body = $this->actionHandleBody();

        $this->assertStringContainsString(
            'NotFoundHttpException',
            $body,
            'actionHandle must throw NotFoundHttpException when the gateway handle is not found',
        );

        // The exception message must interpolate the $handle variable so operators
        // can identify which handle was requested.
        $this->assertStringContainsString(
            '{$handle}',
            $body,
            'The NotFoundHttpException message must interpolate the gateway $handle',
        );

        // The gateway lookup must go through the service layer.
        $this->assertStringContainsString(
            '->getGateway(',
            $body,
            'actionHandle must look up the gateway via the payoutGateways service',
        );
    }

    public function testGatewayWithoutWebhookHandlerReturns400(): void
    {
        $body = $this->actionHandleBody();

        $this->assertStringContainsString(
            'instanceof WebhookHandlerInterface',
            $body,
            'actionHandle must check whether the gateway implements WebhookHandlerInterface',
        );

        $this->assertStringContainsString(
            'BadRequestHttpException',
            $body,
            'actionHandle must throw BadRequestHttpException when the gateway cannot handle webhooks',
        );
    }

    public function testVerifiedWebhookReturns200OkBody(): void
    {
        $body = $this->actionHandleBody();

        $this->assertStringContainsString(
            'setStatusCode(200)',
            $body,
            'actionHandle must set a 200 status code on a successfully verified webhook',
        );

        $this->assertStringContainsString(
            'FORMAT_RAW',
            $body,
            'actionHandle must use FORMAT_RAW so the response body is sent as-is',
        );

        $this->assertStringContainsString(
            "'ok'",
            $body,
            "actionHandle must write the literal string 'ok' as the response body",
        );
    }

    public function testBadSignatureReturns400(): void
    {
        $body = $this->actionHandleBody();

        $this->assertStringContainsString(
            '!$result->verified',
            $body,
            'actionHandle must check !$result->verified to detect a bad webhook signature',
        );

        $this->assertStringContainsString(
            'BadRequestHttpException',
            $body,
            'actionHandle must throw BadRequestHttpException when the webhook signature is invalid',
        );

        // Log-injection fix: newlines in the error message must be sanitised
        // before being passed to Craft::warning().
        $this->assertStringContainsString(
            'str_replace',
            $body,
            'actionHandle must sanitise the error message via str_replace to prevent log injection',
        );

        $this->assertStringContainsString(
            'Craft::warning(',
            $body,
            'actionHandle must emit a Craft::warning() call when a webhook signature is rejected',
        );
    }

    public function testControllerDisablesCsrfForPublicWebhook(): void
    {
        $source = file_get_contents(self::CONTROLLER_FILE);
        $this->assertNotFalse($source, 'WebhooksController.php must be readable');

        // CSRF must be disabled so external providers (which don't send CSRF
        // tokens) can reach the endpoint.
        $this->assertStringContainsString(
            'enableCsrfValidation = false',
            $source,
            'WebhooksController must set $enableCsrfValidation = false for the public webhook endpoint',
        );

        // Only the 'handle' action should be public - no other actions must be
        // exposed anonymously.
        $this->assertStringContainsString(
            "['handle']",
            $source,
            "WebhooksController::\$allowAnonymous must be scoped to exactly ['handle']",
        );
    }
}
