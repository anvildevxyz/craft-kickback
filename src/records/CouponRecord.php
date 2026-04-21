<?php

declare(strict_types=1);

namespace anvildev\craftkickback\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int $affiliateId
 * @property int $discountId
 * @property string $code
 * @property bool $isVanity
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 */
class CouponRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%kickback_coupons}}';
    }
}
