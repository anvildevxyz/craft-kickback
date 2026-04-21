<?php

declare(strict_types=1);

namespace anvildev\craftkickback\gql\arguments\elements;

use craft\gql\base\ElementArguments;
use GraphQL\Type\Definition\Type;

class ReferralArguments extends ElementArguments
{
    public static function getArguments(): array
    {
        return array_merge(parent::getArguments(), [
            'affiliateId' => [
                'name' => 'affiliateId',
                'type' => Type::int(),
                'description' => 'Narrows results to referrals belonging to the given affiliate ID.',
            ],
            'referralStatus' => [
                'name' => 'referralStatus',
                'type' => Type::string(),
                'description' => 'Narrows results by referral status (pending, approved, flagged, rejected, or paid).',
            ],
            'programId' => [
                'name' => 'programId',
                'type' => Type::int(),
                'description' => 'Narrows results to referrals belonging to the given program ID.',
            ],
        ]);
    }
}
