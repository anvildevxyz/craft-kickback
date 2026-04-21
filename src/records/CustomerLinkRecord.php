<?php

declare(strict_types=1);

namespace anvildev\craftkickback\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int $affiliateId
 * @property string $customerEmail
 * @property int|null $customerId
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 */
class CustomerLinkRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%kickback_customer_links}}';
    }
}
