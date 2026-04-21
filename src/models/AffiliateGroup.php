<?php

declare(strict_types=1);

namespace anvildev\craftkickback\models;

use craft\base\Model;
use DateTime;

class AffiliateGroup extends Model
{
    public ?int $id = null;
    public string $name = '';
    public string $handle = '';
    public float $commissionRate = 0.0;
    public string $commissionType = Commission::RATE_TYPE_PERCENTAGE;
    public int $sortOrder = 0;
    public ?DateTime $dateCreated = null;
    public ?DateTime $dateUpdated = null;
    public ?string $uid = null;

    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['name', 'handle', 'commissionRate'], 'required'];
        $rules[] = [['name'], 'string', 'max' => 255];
        $rules[] = [['handle'], 'string', 'max' => 64];
        $rules[] = [['handle'], 'match', 'pattern' => '/^[a-zA-Z][a-zA-Z0-9_]*$/'];
        $rules[] = [['commissionRate'], 'number', 'min' => 0];
        $rules[] = [['commissionType'], 'in', 'range' => Commission::RATE_TYPES];
        $rules[] = [['sortOrder'], 'integer'];

        return $rules;
    }
}
