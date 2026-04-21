<?php

declare(strict_types=1);

namespace anvildev\craftkickback\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property string $targetType
 * @property int $targetId
 * @property string $status          'pending' | 'approved' | 'rejected'
 * @property int|null $requestedUserId
 * @property int|null $resolvedUserId
 * @property string|null $resolvedAt
 * @property string|null $note
 * @property string $dateCreated
 * @property string $dateUpdated
 * @property string $uid
 */
class ApprovalRecord extends ActiveRecord
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public static function tableName(): string
    {
        return '{{%kickback_approvals}}';
    }
}
