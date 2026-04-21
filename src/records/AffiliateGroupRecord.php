<?php

declare(strict_types=1);

namespace anvildev\craftkickback\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property string $name
 * @property string $handle
 * @property float $commissionRate
 * @property string $commissionType
 * @property int $sortOrder
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 */
class AffiliateGroupRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%kickback_affiliate_groups}}';
    }
}
