<?php

declare(strict_types=1);

namespace anvildev\craftkickback\gql\types\generators;

use anvildev\craftkickback\gql\interfaces\elements\PayoutInterface;
use anvildev\craftkickback\gql\types\elements\PayoutType;
use craft\gql\base\GeneratorInterface;
use craft\gql\GqlEntityRegistry;

class PayoutTypeGenerator implements GeneratorInterface
{
    public static function generateTypes(mixed $context = null): array
    {
        $n = static::getName();
        return [$n => GqlEntityRegistry::getEntity($n) ?: GqlEntityRegistry::createEntity($n, new PayoutType([
            'name' => $n,
            'fields' => PayoutInterface::class . '::getFieldDefinitions',
        ]))];
    }

    public static function getName(mixed $context = null): string
    {
        return 'KickbackPayout';
    }
}
