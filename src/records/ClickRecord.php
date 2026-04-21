<?php

declare(strict_types=1);

namespace anvildev\craftkickback\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int $affiliateId
 * @property int $programId
 * @property string $ip
 * @property string|null $userAgent
 * @property string|null $referrerUrl
 * @property string $landingUrl
 * @property string|null $subId
 * @property bool $isUnique
 * @property string $dateCreated
 */
class ClickRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%kickback_clicks}}';
    }
}
