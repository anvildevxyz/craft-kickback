<?php

declare(strict_types=1);

namespace anvildev\craftkickback\services;

use anvildev\craftkickback\elements\AffiliateElement;
use anvildev\craftkickback\events\AffiliateEvent;
use anvildev\craftkickback\events\FraudEvent;
use anvildev\craftkickback\events\PayoutEvent;
use anvildev\craftkickback\KickBack;
use Craft;
use craft\base\Component;
use craft\helpers\App;
use craft\mail\Message;

/**
 * Sends email notifications for affiliate approvals, rejections, payouts, and fraud alerts.
 */
class NotificationService extends Component
{
    public function onAffiliateApproved(AffiliateEvent $event): void
    {
        $affiliate = $event->affiliate;
        $user = $affiliate->getUser();
        if (empty($user?->email)) {
            return;
        }

        $plugin = KickBack::getInstance();
        $portalPath = $plugin->getSettings()->getCurrentSitePortalPath();
        $portalUrl = rtrim(Craft::$app->getSites()->getCurrentSite()->getBaseUrl(), '/')
            . ($portalPath !== null ? '/' . $portalPath : '');

        $parent = $affiliate->parentAffiliateId !== null
            ? $plugin->affiliates->getAffiliateById($affiliate->parentAffiliateId) : null;

        $this->sendEmail($user->email,
            Craft::t('kickback', 'Your affiliate application has been approved!'),
            $plugin->emailRender->render('approval', [
                'name' => $user->friendlyName ?? $user->email,
                'portalUrl' => $portalUrl,
                'recruiterName' => $parent instanceof AffiliateElement ? $parent->title : null,
            ]),
        );
    }

    public function onAffiliateRejected(AffiliateEvent $event): void
    {
        $user = $event->affiliate->getUser();
        if (empty($user?->email)) {
            return;
        }
        $this->sendEmail($user->email,
            Craft::t('kickback', 'Update on your affiliate application'),
            KickBack::getInstance()->emailRender->render('rejection', ['name' => $user->friendlyName ?? $user->email]),
        );
    }

    public function onPayoutCompleted(PayoutEvent $event): void
    {
        $user = $event->affiliate?->getUser();
        if (empty($user?->email)) {
            return;
        }

        $payout = $event->payout;
        $amt = Craft::$app->getFormatter()->asCurrency($payout->amount, $payout->currency);
        $this->sendEmail($user->email,
            Craft::t('kickback', 'Payout of {amount} has been processed', ['amount' => $amt]),
            KickBack::getInstance()->emailRender->render('payout', [
                'name' => $user->friendlyName ?? $user->email,
                'amount' => $amt,
                'method' => ucfirst(str_replace('_', ' ', $payout->method)),
            ]),
        );
    }

    public function onReferralFlagged(FraudEvent $event): void
    {
        $adminEmail = self::normalizeEmail(App::mailSettings()->fromEmail ?? null);
        if ($adminEmail === null) {
            Craft::warning('Skipping fraud alert email: mail fromEmail is empty, unresolved, or invalid.', __METHOD__);
            return;
        }

        $ref = $event->referral;
        $this->sendEmail($adminEmail,
            Craft::t('kickback', '[Kickback] Fraud alert: Referral #{id} flagged', ['id' => $ref->id]),
            KickBack::getInstance()->emailRender->render('fraud-alert', [
                'referralId' => $ref->id, 'affiliateId' => $ref->affiliateId,
                'flags' => implode(', ', $event->fraudFlags),
            ]),
        );
    }

    private function sendEmail(string $to, string $subject, string $html): void
    {
        $recipient = self::normalizeEmail($to);
        if ($recipient === null) {
            Craft::warning(
                "Skipping Kickback notification: unresolved or invalid recipient '{$to}'",
                __METHOD__,
            );
            return;
        }

        try {
            $message = new Message();
            $message->setTo($recipient);
            $message->setSubject($subject);
            $message->setHtmlBody($html);

            Craft::$app->getMailer()->send($message);
        } catch (\Throwable $e) {
            Craft::warning("Failed to send Kickback notification to {$recipient}: {$e->getMessage()}", __METHOD__);
        }
    }

    public static function normalizeEmail(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $parsed = trim((string)App::parseEnv($raw));
        if ($parsed === '' || str_starts_with($parsed, '$')) {
            return null;
        }

        return filter_var($parsed, FILTER_VALIDATE_EMAIL) !== false ? $parsed : null;
    }
}
