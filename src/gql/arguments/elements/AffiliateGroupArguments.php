<?php

declare(strict_types=1);

namespace anvildev\craftkickback\gql\arguments\elements;

use craft\gql\base\ElementArguments;
use GraphQL\Type\Definition\Type;

class AffiliateGroupArguments extends ElementArguments
{
    public static function getArguments(): array
    {
        return array_merge(parent::getArguments(), [
            'handle' => [
                'name' => 'handle',
                'type' => Type::string(),
                'description' => 'Narrows results to the affiliate group with the given handle.',
            ],
            'commissionType' => [
                'name' => 'commissionType',
                'type' => Type::string(),
                'description' => 'Narrows results by commission type (percentage or flat).',
            ],
        ]);
    }
}
