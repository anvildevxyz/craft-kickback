<?php

declare(strict_types=1);

namespace anvildev\craftkickback\gql\arguments\elements;

use craft\gql\base\ElementArguments;
use GraphQL\Type\Definition\Type;

class ProgramArguments extends ElementArguments
{
    public static function getArguments(): array
    {
        return array_merge(parent::getArguments(), [
            'handle' => [
                'name' => 'handle',
                'type' => Type::string(),
                'description' => 'Narrows results to the program with the given handle.',
            ],
            'programStatus' => [
                'name' => 'programStatus',
                'type' => Type::string(),
                'description' => 'Narrows results by program status (active, inactive, or archived).',
            ],
        ]);
    }
}
