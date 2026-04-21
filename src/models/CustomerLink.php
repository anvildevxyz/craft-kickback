<?php

declare(strict_types=1);

namespace anvildev\craftkickback\models;

use craft\base\Model;
use DateTime;

/**
 * Represents a link between a customer and an affiliate for lifetime commission tracking.
 */
class CustomerLink extends Model
{
    public ?int $id = null;
    public ?int $affiliateId = null;
    public string $customerEmail = '';
    public ?int $customerId = null;
    public ?DateTime $dateCreated = null;
    public ?DateTime $dateUpdated = null;
    public ?string $uid = null;

    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['affiliateId', 'customerEmail'], 'required'];
        $rules[] = [['affiliateId', 'customerId'], 'integer'];
        $rules[] = [['customerEmail'], 'email'];
        $rules[] = [['customerEmail'], 'string', 'max' => 255];

        return $rules;
    }
}
