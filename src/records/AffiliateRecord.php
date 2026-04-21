<?php

declare(strict_types=1);

namespace anvildev\craftkickback\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int $userId
 * @property int $programId
 * @property string $status
 * @property string $referralCode
 * @property float|null $commissionRateOverride
 * @property string|null $commissionTypeOverride
 * @property int|null $parentAffiliateId
 * @property int $tierLevel
 * @property int|null $groupId
 * @property string|null $paypalEmail
 * @property string|null $stripeAccountId
 * @property string $payoutMethod
 * @property float $payoutThreshold
 * @property float $lifetimeEarnings
 * @property int $lifetimeReferrals
 * @property float $pendingBalance
 * @property string|null $notes
 * @property string|null $dateApproved
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 */
class AffiliateRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%kickback_affiliates}}';
    }
}
