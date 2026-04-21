<?php

declare(strict_types=1);

namespace anvildev\craftkickback\gql\arguments\elements;

use craft\gql\base\ElementArguments;
use GraphQL\Type\Definition\Type;

class PayoutArguments extends ElementArguments
{
    public static function getArguments(): array
    {
        return array_merge(parent::getArguments(), [
            'affiliateId' => [
                'name' => 'affiliateId',
                'type' => Type::int(),
                'description' => 'Narrows results to payouts belonging to the given affiliate ID.',
            ],
            'payoutStatus' => [
                'name' => 'payoutStatus',
                'type' => Type::string(),
                'description' => 'Narrows results by payout status (pending, processing, completed, failed, rejected, or reversed).',
            ],
            'method' => [
                'name' => 'method',
                'type' => Type::string(),
                'description' => 'Narrows results by payout method (paypal, stripe, store_credit, or manual).',
            ],
        ]);
    }
}
