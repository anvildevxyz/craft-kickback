<?php

declare(strict_types=1);

namespace anvildev\craftkickback\gql\types\generators;

use anvildev\craftkickback\gql\interfaces\elements\AffiliateGroupInterface;
use anvildev\craftkickback\gql\types\elements\AffiliateGroupType;
use craft\gql\base\GeneratorInterface;
use craft\gql\GqlEntityRegistry;

class AffiliateGroupTypeGenerator implements GeneratorInterface
{
    public static function generateTypes(mixed $context = null): array
    {
        $n = static::getName();
        return [$n => GqlEntityRegistry::getEntity($n) ?: GqlEntityRegistry::createEntity($n, new AffiliateGroupType([
            'name' => $n,
            'fields' => AffiliateGroupInterface::class . '::getFieldDefinitions',
        ]))];
    }

    public static function getName(mixed $context = null): string
    {
        return 'KickbackAffiliateGroup';
    }
}
