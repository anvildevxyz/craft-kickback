<?php

declare(strict_types=1);

namespace anvildev\craftkickback\elements\actions;

use anvildev\craftkickback\KickBack;
use anvildev\craftkickback\records\ReferralRecord;
use Craft;
use craft\base\ElementAction;
use craft\elements\db\ElementQueryInterface;

class ApproveFraudAction extends ElementAction
{
    public function getTriggerLabel(): string
    {
        return Craft::t('kickback', 'Approve (clear fraud flag)');
    }

    public function getConfirmationMessage(): ?string
    {
        return Craft::t('kickback', 'Are you sure you want to approve the selected flagged referrals? Their commissions will be approved for payout.');
    }

    public function performAction(ElementQueryInterface $query): bool
    {
        $user = Craft::$app->getUser()->getIdentity();
        if (!$user?->can(KickBack::PERMISSION_APPROVE_REFERRALS)) {
            $this->setMessage(Craft::t('kickback', 'You do not have permission to approve referrals.'));
            return false;
        }

        $fraud = KickBack::getInstance()->fraud;
        $success = 0;

        foreach ($query->all() as $element) {
            $record = ReferralRecord::findOne($element->id);
            if ($record !== null && $fraud->approveFlaggedReferral($record)) {
                $success++;
            }
        }

        if ($success === 0) {
            $this->setMessage(Craft::t('kickback', 'Could not approve any of the selected referrals.'));
            return false;
        }

        $this->setMessage(Craft::t('kickback', '{count} referral(s) approved.', ['count' => $success]));
        return true;
    }
}
