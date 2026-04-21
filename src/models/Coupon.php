<?php

declare(strict_types=1);

namespace anvildev\craftkickback\models;

use craft\base\Model;
use DateTime;

class Coupon extends Model
{
    public ?int $id = null;
    public ?int $affiliateId = null;
    public ?int $discountId = null;
    public string $code = '';
    public bool $isVanity = false;
    public ?DateTime $dateCreated = null;
    public ?DateTime $dateUpdated = null;
    public ?string $uid = null;

    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['affiliateId', 'discountId', 'code'], 'required'];
        $rules[] = [['affiliateId', 'discountId'], 'integer'];
        $rules[] = [['code'], 'string', 'max' => 64];

        return $rules;
    }
}
