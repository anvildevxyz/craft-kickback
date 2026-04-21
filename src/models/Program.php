<?php

declare(strict_types=1);

namespace anvildev\craftkickback\models;

use anvildev\craftkickback\enums\ProgramStatus;
use craft\base\Model;
use DateTime;

class Program extends Model
{
    public const STATUS_ACTIVE = ProgramStatus::Active->value;
    public const STATUS_INACTIVE = ProgramStatus::Inactive->value;
    public const STATUS_ARCHIVED = ProgramStatus::Archived->value;

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_ARCHIVED,
    ];

    public ?int $id = null;
    public string $name = '';
    public string $handle = '';
    public ?string $description = null;
    public float $defaultCommissionRate = 10.0;
    public string $defaultCommissionType = 'percentage';
    public int $cookieDuration = 30;
    public bool $allowSelfReferral = false;
    public string $status = 'active';
    public ?string $termsAndConditions = null;
    public ?DateTime $dateCreated = null;
    public ?DateTime $dateUpdated = null;
    public ?string $uid = null;

    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['name', 'handle'], 'required'];
        $rules[] = [['name'], 'string', 'max' => 255];
        $rules[] = [['handle'], 'string', 'max' => 64];
        $rules[] = [['handle'], 'match', 'pattern' => '/^[a-zA-Z][a-zA-Z0-9_]*$/'];
        $rules[] = [['defaultCommissionRate'], 'number', 'min' => 0];
        $rules[] = [['defaultCommissionType'], 'in', 'range' => Commission::RATE_TYPES];
        $rules[] = [['cookieDuration'], 'integer', 'min' => 1];
        $rules[] = [['status'], 'in', 'range' => self::STATUSES];

        return $rules;
    }
}
