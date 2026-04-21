<?php

declare(strict_types=1);

namespace anvildev\craftkickback\gql\arguments\elements;

use craft\gql\base\ElementArguments;
use GraphQL\Type\Definition\Type;

class CommissionRuleArguments extends ElementArguments
{
    public static function getArguments(): array
    {
        return array_merge(parent::getArguments(), [
            'programId' => [
                'name' => 'programId',
                'type' => Type::int(),
                'description' => 'Narrows results to rules belonging to the given program ID.',
            ],
            'type' => [
                'name' => 'type',
                'type' => Type::string(),
                'description' => 'Narrows results by rule type (product, category, tiered, bonus, or mlm_tier).',
            ],
            'targetId' => [
                'name' => 'targetId',
                'type' => Type::int(),
                'description' => 'Narrows results to rules targeting the given product or category ID.',
            ],
            'tierLevel' => [
                'name' => 'tierLevel',
                'type' => Type::int(),
                'description' => 'Narrows results to rules for the given MLM tier level.',
            ],
        ]);
    }
}
