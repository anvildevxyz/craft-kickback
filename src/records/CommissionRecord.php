<?php

declare(strict_types=1);

namespace anvildev\craftkickback\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int $referralId
 * @property int $affiliateId
 * @property float $amount
 * @property float $originalAmount
 * @property string $currency
 * @property float $rate
 * @property string $rateType
 * @property string|null $ruleApplied
 * @property string|null $ruleResolutionTrace
 * @property int $tier
 * @property string $status
 * @property int|null $payoutId
 * @property string|null $description
 * @property string|null $dateApproved
 * @property string|null $dateReversed
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 */
class CommissionRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%kickback_commissions}}';
    }
}
