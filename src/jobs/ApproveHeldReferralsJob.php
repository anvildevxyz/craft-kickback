<?php

declare(strict_types=1);

namespace anvildev\craftkickback\jobs;

use anvildev\craftkickback\helpers\DateHelper;
use anvildev\craftkickback\KickBack;
use anvildev\craftkickback\models\Commission;
use anvildev\craftkickback\models\Referral;
use anvildev\craftkickback\records\ReferralRecord;
use Craft;
use craft\queue\BaseJob;

/**
 * Auto-approve pending referrals whose hold period has elapsed, and
 * cascade approval to their pending commissions.
 */
class ApproveHeldReferralsJob extends BaseJob
{
    public function execute($queue): void
    {
        $plugin = KickBack::getInstance();
        $settings = $plugin->getSettings();
        $holdDays = $settings->holdPeriodDays;

        if ($holdDays <= 0) {
            return;
        }

        $cutoff = DateHelper::pastCutoffString("-{$holdDays} days");

        /** @var ReferralRecord[] $pendingReferrals */
        $pendingReferrals = ReferralRecord::find()
            ->where(['status' => Referral::STATUS_PENDING])
            ->andWhere(['<=', 'dateCreated', $cutoff])
            ->all();

        $total = count($pendingReferrals);
        if ($total === 0) {
            return;
        }

        $approved = 0;
        $failed = 0;

        foreach ($pendingReferrals as $i => $referral) {
            $this->setProgress($queue, ($i + 1) / $total, "Approving referral #{$referral->id}");

            try {
                if ($plugin->referrals->approveReferral($referral)) {
                    $commissions = $plugin->commissions->getCommissionsByReferralId($referral->id);
                    foreach ($commissions as $commission) {
                        if ($commission->status === Commission::STATUS_PENDING) {
                            $plugin->commissions->approveCommission($commission);
                        }
                    }
                    $approved++;
                } else {
                    $failed++;
                }
            } catch (\Throwable $e) {
                $failed++;
                Craft::error(
                    "Auto-approve failed for referral #{$referral->id}: {$e->getMessage()}",
                    __METHOD__
                );
            }
        }

        Craft::info("Hold-period auto-approval complete: {$approved} approved, {$failed} failed out of {$total} eligible", __METHOD__);
    }

    protected function defaultDescription(): ?string
    {
        return Craft::t('kickback', 'Auto-approving held referrals');
    }
}
