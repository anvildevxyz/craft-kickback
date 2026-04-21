<?php

declare(strict_types=1);

namespace anvildev\craftkickback\models;

use anvildev\craftkickback\enums\CommissionStatus;
use anvildev\craftkickback\enums\RateType;
use craft\base\Model;
use DateTime;

class Commission extends Model
{
    public const STATUS_PENDING = CommissionStatus::Pending->value;
    public const STATUS_APPROVED = CommissionStatus::Approved->value;
    public const STATUS_PAID = CommissionStatus::Paid->value;
    public const STATUS_REVERSED = CommissionStatus::Reversed->value;
    public const STATUS_REJECTED = CommissionStatus::Rejected->value;

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_PAID,
        self::STATUS_REVERSED,
        self::STATUS_REJECTED,
    ];

    public const RATE_TYPE_PERCENTAGE = RateType::Percentage->value;
    public const RATE_TYPE_FLAT = RateType::Flat->value;

    public const RATE_TYPES = [
        self::RATE_TYPE_PERCENTAGE,
        self::RATE_TYPE_FLAT,
    ];

    public ?int $id = null;
    public ?int $referralId = null;
    public ?int $affiliateId = null;
    public float $amount = 0.0;
    public string $currency = 'USD';
    public float $rate = 0.0;
    public string $rateType = '';
    public ?string $ruleApplied = null;
    public int $tier = 1;
    public string $status = self::STATUS_PENDING;
    public ?int $payoutId = null;
    public ?string $description = null;
    public ?DateTime $dateCreated = null;
    public ?DateTime $dateUpdated = null;
    public ?string $uid = null;

    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['referralId', 'affiliateId', 'amount', 'rate', 'rateType'], 'required'];
        $rules[] = [['referralId', 'affiliateId', 'payoutId'], 'integer'];
        $rules[] = [['amount', 'rate'], 'number', 'min' => 0];
        $rules[] = [['rateType'], 'in', 'range' => self::RATE_TYPES];
        $rules[] = [['status'], 'in', 'range' => self::STATUSES];
        $rules[] = [['currency'], 'string', 'length' => 3];
        $rules[] = [['ruleApplied', 'description'], 'string', 'max' => 255];
        $rules[] = [['tier'], 'integer', 'min' => 1];

        return $rules;
    }
}
