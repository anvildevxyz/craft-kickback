<?php

declare(strict_types=1);

namespace anvildev\craftkickback\controllers;

use anvildev\craftkickback\gateways\WebhookHandlerInterface;
use anvildev\craftkickback\KickBack;
use Craft;
use craft\web\Controller;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Public webhook receiver. Routes kickback/webhooks/<handle> to the
 * matching gateway's WebhookHandlerInterface. CSRF is disabled because
 * providers cannot send CSRF tokens - authenticity is enforced via each
 * gateway's own signature verification.
 */
class WebhooksController extends Controller
{
    protected array|bool|int $allowAnonymous = ['handle'];
    public $enableCsrfValidation = false;

    public function actionHandle(string $handle): Response
    {
        $this->requirePostRequest();

        $gateway = KickBack::getInstance()->payoutGateways->getGateway($handle);
        if ($gateway === null) {
            throw new NotFoundHttpException("Unknown gateway: {$handle}");
        }

        if (!$gateway instanceof WebhookHandlerInterface) {
            throw new BadRequestHttpException("Gateway {$handle} does not accept webhooks.");
        }

        $request = Craft::$app->getRequest();
        $headers = array_map(fn($v) => $v[0] ?? '', iterator_to_array($request->getHeaders()));

        $result = $gateway->handleWebhook($request->getRawBody(), $headers);

        if (!$result->verified) {
            $safeError = str_replace(["\r", "\n"], ' ', $result->errorMessage ?? '');
            Craft::warning("Rejected webhook for {$handle}: {$safeError}", __METHOD__);
            throw new BadRequestHttpException('Invalid webhook signature.');
        }

        if ($result->processed) {
            Craft::info("Webhook for {$handle} processed payout #{$result->payoutId}", __METHOD__);
        } else {
            Craft::info("Webhook for {$handle} verified but not processed (unknown reference)", __METHOD__);
        }

        $response = Craft::$app->getResponse();
        $response->setStatusCode(200);
        $response->format = Response::FORMAT_RAW;
        $response->content = 'ok';
        return $response;
    }
}
