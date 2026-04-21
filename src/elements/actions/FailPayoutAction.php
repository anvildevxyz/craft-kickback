<?php

declare(strict_types=1);

namespace anvildev\craftkickback\elements\actions;

use anvildev\craftkickback\elements\PayoutElement;
use anvildev\craftkickback\KickBack;
use Craft;
use craft\base\ElementAction;
use craft\elements\db\ElementQueryInterface;

class FailPayoutAction extends ElementAction
{
    public function getTriggerLabel(): string
    {
        return Craft::t('kickback', 'Mark as failed');
    }

    public function getConfirmationMessage(): ?string
    {
        return Craft::t('kickback', 'Mark the selected payouts as failed?');
    }

    public function performAction(ElementQueryInterface $query): bool
    {
        $user = Craft::$app->getUser()->getIdentity();
        if (!$user?->can(KickBack::PERMISSION_PROCESS_PAYOUTS)) {
            $this->setMessage(Craft::t('kickback', 'You do not have permission to process payouts.'));
            return false;
        }

        $payouts = KickBack::getInstance()->payouts;
        $success = 0;

        /** @var PayoutElement $payout */
        foreach ($query->all() as $payout) {
            if ($payouts->failPayout($payout)) {
                $success++;
            }
        }

        if ($success === 0) {
            $this->setMessage(Craft::t('kickback', 'Could not fail any of the selected payouts.'));
            return false;
        }

        $this->setMessage(Craft::t('kickback', '{count} payout(s) marked as failed.', ['count' => $success]));
        return true;
    }
}
