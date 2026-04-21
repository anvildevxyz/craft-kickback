<?php

declare(strict_types=1);

namespace anvildev\craftkickback\gql\types\generators;

use anvildev\craftkickback\gql\interfaces\elements\ProgramInterface;
use anvildev\craftkickback\gql\types\elements\ProgramType;
use craft\gql\base\GeneratorInterface;
use craft\gql\GqlEntityRegistry;

class ProgramTypeGenerator implements GeneratorInterface
{
    public static function generateTypes(mixed $context = null): array
    {
        $n = static::getName();
        return [$n => GqlEntityRegistry::getEntity($n) ?: GqlEntityRegistry::createEntity($n, new ProgramType([
            'name' => $n,
            'fields' => ProgramInterface::class . '::getFieldDefinitions',
        ]))];
    }

    public static function getName(mixed $context = null): string
    {
        return 'KickbackProgram';
    }
}
