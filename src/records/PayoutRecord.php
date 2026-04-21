<?php

declare(strict_types=1);

namespace anvildev\craftkickback\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int $affiliateId
 * @property int|null $createdByUserId
 * @property float $amount
 * @property string $currency
 * @property string $method
 * @property string $status
 * @property string|null $transactionId
 * @property string|null $gatewayBatchId
 * @property string|null $notes
 * @property string|null $processedAt
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 */
class PayoutRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%kickback_payouts}}';
    }
}
