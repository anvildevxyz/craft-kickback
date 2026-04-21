<?php

declare(strict_types=1);

namespace anvildev\craftkickback\gql\types\generators;

use anvildev\craftkickback\gql\interfaces\elements\CommissionInterface;
use anvildev\craftkickback\gql\types\elements\CommissionType;
use craft\gql\base\GeneratorInterface;
use craft\gql\GqlEntityRegistry;

class CommissionTypeGenerator implements GeneratorInterface
{
    public static function generateTypes(mixed $context = null): array
    {
        $n = static::getName();
        return [$n => GqlEntityRegistry::getEntity($n) ?: GqlEntityRegistry::createEntity($n, new CommissionType([
            'name' => $n,
            'fields' => CommissionInterface::class . '::getFieldDefinitions',
        ]))];
    }

    public static function getName(mixed $context = null): string
    {
        return 'KickbackCommission';
    }
}
