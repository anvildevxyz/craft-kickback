<?php

declare(strict_types=1);

namespace anvildev\craftkickback\elements\actions;

use anvildev\craftkickback\KickBack;
use anvildev\craftkickback\records\ReferralRecord;
use Craft;
use craft\base\ElementAction;
use craft\elements\db\ElementQueryInterface;

class RejectReferralAction extends ElementAction
{
    public function getTriggerLabel(): string
    {
        return Craft::t('kickback', 'Reject referrals');
    }

    public function getConfirmationMessage(): ?string
    {
        return Craft::t('kickback', 'Reject the selected referrals? Their commissions will be cancelled.');
    }

    public function performAction(ElementQueryInterface $query): bool
    {
        $user = Craft::$app->getUser()->getIdentity();
        if (!$user?->can(KickBack::PERMISSION_APPROVE_REFERRALS)) {
            $this->setMessage(Craft::t('kickback', 'You do not have permission to reject referrals.'));
            return false;
        }

        $referrals = KickBack::getInstance()->referrals;
        $success = 0;

        foreach ($query->all() as $element) {
            $record = ReferralRecord::findOne($element->id);
            if ($record !== null && $referrals->rejectReferral($record)) {
                $success++;
            }
        }

        if ($success === 0) {
            $this->setMessage(Craft::t('kickback', 'Could not reject any of the selected referrals.'));
            return false;
        }

        $this->setMessage(Craft::t('kickback', '{count} referral(s) rejected.', ['count' => $success]));
        return true;
    }
}
