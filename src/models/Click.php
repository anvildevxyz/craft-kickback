<?php

declare(strict_types=1);

namespace anvildev\craftkickback\models;

use craft\base\Model;
use DateTime;

class Click extends Model
{
    public ?int $id = null;
    public ?int $affiliateId = null;
    public ?int $programId = null;
    public string $ip = '';
    public ?string $userAgent = null;
    public ?string $referrerUrl = null;
    public string $landingUrl = '';
    public ?string $subId = null;
    public bool $isUnique = true;
    public ?DateTime $dateCreated = null;

    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['affiliateId', 'programId', 'ip', 'landingUrl'], 'required'];
        $rules[] = [['affiliateId', 'programId'], 'integer'];
        $rules[] = [['ip'], 'string', 'max' => 45];
        $rules[] = [['userAgent'], 'string', 'max' => 500];
        $rules[] = [['referrerUrl', 'landingUrl'], 'string', 'max' => 2048];
        $rules[] = [['subId'], 'string', 'max' => 255];

        return $rules;
    }
}
