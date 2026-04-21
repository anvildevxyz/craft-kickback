<?php

declare(strict_types=1);

namespace anvildev\craftkickback\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int $affiliateId
 * @property int $programId
 * @property int|null $orderId
 * @property int|null $clickId
 * @property string|null $customerEmail
 * @property int|null $customerId
 * @property float $orderSubtotal
 * @property string $status
 * @property string $attributionMethod
 * @property string|null $couponCode
 * @property string|null $referralResolutionTrace
 * @property string|null $subId
 * @property string|null $fraudFlags
 * @property string|null $dateApproved
 * @property string|null $datePaid
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 */
class ReferralRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%kickback_referrals}}';
    }
}
