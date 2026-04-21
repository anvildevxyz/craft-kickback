<?php

declare(strict_types=1);

namespace anvildev\craftkickback\gql\arguments\elements;

use craft\gql\base\ElementArguments;
use GraphQL\Type\Definition\Type;

class AffiliateArguments extends ElementArguments
{
    public static function getArguments(): array
    {
        return array_merge(parent::getArguments(), [
            'referralCode' => [
                'name' => 'referralCode',
                'type' => Type::string(),
                'description' => 'Narrows results to the affiliate with the given referral code.',
            ],
            'affiliateStatus' => [
                'name' => 'affiliateStatus',
                'type' => Type::string(),
                'description' => 'Narrows results by affiliate status (active, pending, suspended, rejected).',
            ],
            'programId' => [
                'name' => 'programId',
                'type' => Type::int(),
                'description' => 'Narrows results to affiliates belonging to the given program ID.',
            ],
        ]);
    }
}
