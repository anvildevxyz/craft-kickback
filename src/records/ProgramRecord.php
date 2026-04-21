<?php

declare(strict_types=1);

namespace anvildev\craftkickback\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property string $handle
 * @property float $defaultCommissionRate
 * @property string $defaultCommissionType
 * @property int $cookieDuration
 * @property bool $allowSelfReferral
 * @property bool $enableCouponCreation
 * @property string $propagationMethod
 * @property string $status
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 */
class ProgramRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%kickback_programs}}';
    }
}
