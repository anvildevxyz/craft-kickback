<?php

declare(strict_types=1);

namespace anvildev\craftkickback\gql\types\generators;

use anvildev\craftkickback\gql\interfaces\elements\AffiliateInterface;
use anvildev\craftkickback\gql\types\elements\AffiliateType;
use craft\gql\base\GeneratorInterface;
use craft\gql\GqlEntityRegistry;

class AffiliateTypeGenerator implements GeneratorInterface
{
    public static function generateTypes(mixed $context = null): array
    {
        $n = static::getName();
        return [$n => GqlEntityRegistry::getEntity($n) ?: GqlEntityRegistry::createEntity($n, new AffiliateType([
            'name' => $n,
            'fields' => AffiliateInterface::class . '::getFieldDefinitions',
        ]))];
    }

    public static function getName(mixed $context = null): string
    {
        return 'KickbackAffiliate';
    }
}
