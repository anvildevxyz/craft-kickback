<?php

declare(strict_types=1);

namespace anvildev\craftkickback\console\controllers;

use anvildev\craftkickback\KickBack;
use Craft;
use craft\console\Controller;
use craft\mail\Message;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Send preview emails for all Kickback notification templates.
 *
 * Usage: php craft kickback/email/preview --to=you@example.com
 */
class EmailController extends Controller
{
    public string $to = '';

    public string $type = 'all';

    private const TEMPLATE_TYPES = [
        'approval',
        'rejection',
        'payout',
        'fraud-alert',
    ];

    private const SAMPLE_DATA = [
        'approval' => [
            'name' => 'Jane Doe',
            'portalUrl' => 'https://example.com/affiliate',
            'recruiterName' => 'John Smith',
        ],
        'rejection' => [
            'name' => 'Jane Doe',
        ],
        'payout' => [
            'name' => 'Jane Doe',
            'amount' => '$150.00',
            'method' => 'PayPal',
        ],
        'fraud-alert' => [
            'referralId' => 1234,
            'affiliateId' => 567,
            'flags' => 'click_velocity:15_clicks_from_192.168.1.1, rapid_conversion:3_in_5min',
        ],
    ];

    private const SUBJECTS = [
        'approval' => 'Your affiliate application has been approved!',
        'rejection' => 'Update on your affiliate application',
        'payout' => 'Payout of $150.00 has been processed',
        'fraud-alert' => '[Kickback] Fraud alert: Referral #1234 flagged',
    ];

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['to', 'type']);
    }

    public function actionPreview(): int
    {
        if (empty($this->to)) {
            $this->stderr("Usage: php craft kickback/email/preview --to=you@example.com\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        if (!filter_var($this->to, FILTER_VALIDATE_EMAIL)) {
            $this->stderr("Invalid email address: {$this->to}\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $types = $this->type === 'all' ? self::TEMPLATE_TYPES : [$this->type];

        foreach ($types as $type) {
            if (!in_array($type, self::TEMPLATE_TYPES, true)) {
                $this->stderr("Unknown template type: {$type}\n", Console::FG_RED);
                $this->stderr("Available types: " . implode(', ', self::TEMPLATE_TYPES) . "\n");
                return ExitCode::UNSPECIFIED_ERROR;
            }
        }

        $emailRender = KickBack::getInstance()->emailRender;

        $this->stdout("Sending preview emails to {$this->to}\n\n");

        $sent = 0;
        $failed = 0;

        foreach ($types as $type) {
            $this->stdout("  {$type} ... ");

            try {
                $html = $emailRender->render($type, self::SAMPLE_DATA[$type]);
                $subject = '[Preview] ' . self::SUBJECTS[$type];

                $message = new Message();
                $message->setTo($this->to);
                $message->setSubject($subject);
                $message->setHtmlBody($html);

                if (Craft::$app->getMailer()->send($message)) {
                    $this->stdout("OK\n", Console::FG_GREEN);
                    $sent++;
                } else {
                    $this->stdout("SEND FAILED\n", Console::FG_RED);
                    $failed++;
                }
            } catch (\Throwable $e) {
                $this->stdout("ERROR\n", Console::FG_RED);
                $this->stderr("    {$e->getMessage()}\n", Console::FG_YELLOW);
                $failed++;
            }
        }

        $this->stdout("\nSent: {$sent}", Console::FG_GREEN);
        $this->stdout(" | ");
        $this->stdout("Failed: {$failed}", $failed > 0 ? Console::FG_RED : Console::FG_GREEN);
        $this->stdout("\n");

        return $failed > 0 ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    public function actionList(): int
    {
        $this->stdout("Available Kickback email templates:\n\n");

        foreach (self::TEMPLATE_TYPES as $type) {
            $this->stdout("  - {$type}\n");
        }

        $this->stdout("\nOverride any template by placing your version at:\n");
        $this->stdout("  templates/_kickback/emails/{type}.twig\n\n");

        return ExitCode::OK;
    }
}
