<?php

declare(strict_types=1);

namespace anvildev\craftkickback\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int $programId
 * @property string $name
 * @property string $type
 * @property int|null $targetId
 * @property float $commissionRate
 * @property string $commissionType
 * @property int|null $tierThreshold
 * @property int|null $tierLevel
 * @property int|null $lookbackDays
 * @property int $priority
 * @property string|null $conditions
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 */
class CommissionRuleRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%kickback_commission_rules}}';
    }
}
