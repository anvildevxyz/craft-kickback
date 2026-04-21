<?php

declare(strict_types=1);

namespace anvildev\craftkickback\models;

use anvildev\craftkickback\enums\CommissionRuleType;
use craft\base\Model;
use DateTime;

class CommissionRule extends Model
{
    public const TYPE_PRODUCT = CommissionRuleType::Product->value;
    public const TYPE_CATEGORY = CommissionRuleType::Category->value;
    public const TYPE_TIERED = CommissionRuleType::Tiered->value;
    public const TYPE_BONUS = CommissionRuleType::Bonus->value;
    public const TYPE_MLM_TIER = CommissionRuleType::MlmTier->value;

    public const TYPES = [
        self::TYPE_PRODUCT,
        self::TYPE_CATEGORY,
        self::TYPE_TIERED,
        self::TYPE_BONUS,
        self::TYPE_MLM_TIER,
    ];

    public ?int $id = null;
    public ?int $programId = null;
    public string $name = '';
    public string $type = '';
    public ?int $targetId = null;
    public float $commissionRate = 0.0;
    public string $commissionType = Commission::RATE_TYPE_PERCENTAGE;
    public ?int $tierThreshold = null;
    public ?int $tierLevel = null;
    public ?int $lookbackDays = null;
    public int $priority = 0;
    /** @var array<string, mixed>|null */
    public ?array $conditions = null;
    public ?DateTime $dateCreated = null;
    public ?DateTime $dateUpdated = null;
    public ?string $uid = null;

    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['name', 'type', 'programId', 'commissionRate'], 'required'];
        $rules[] = [['name'], 'string', 'max' => 255];
        $rules[] = [['type'], 'in', 'range' => self::TYPES];
        $rules[] = [['programId', 'targetId', 'tierThreshold', 'tierLevel', 'lookbackDays', 'priority'], 'integer'];
        $rules[] = [['commissionRate'], 'number', 'min' => 0];
        $rules[] = [['commissionType'], 'in', 'range' => Commission::RATE_TYPES];

        return $rules;
    }
}
