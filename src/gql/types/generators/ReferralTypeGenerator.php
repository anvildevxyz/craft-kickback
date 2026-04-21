<?php

declare(strict_types=1);

namespace anvildev\craftkickback\gql\types\generators;

use anvildev\craftkickback\gql\interfaces\elements\ReferralInterface;
use anvildev\craftkickback\gql\types\elements\ReferralType;
use craft\gql\base\GeneratorInterface;
use craft\gql\GqlEntityRegistry;

class ReferralTypeGenerator implements GeneratorInterface
{
    public static function generateTypes(mixed $context = null): array
    {
        $n = static::getName();
        return [$n => GqlEntityRegistry::getEntity($n) ?: GqlEntityRegistry::createEntity($n, new ReferralType([
            'name' => $n,
            'fields' => ReferralInterface::class . '::getFieldDefinitions',
        ]))];
    }

    public static function getName(mixed $context = null): string
    {
        return 'KickbackReferral';
    }
}
