<?php

declare(strict_types=1);

namespace anvildev\craftkickback\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int $siteId
 * @property string $name
 * @property string|null $description
 * @property string|null $termsAndConditions
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 */
class ProgramSiteRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%kickback_programs_sites}}';
    }

    public static function primaryKey(): array
    {
        return ['id', 'siteId'];
    }
}
