<?php

declare(strict_types=1);

namespace anvildev\craftkickback\gql\types\generators;

use anvildev\craftkickback\gql\interfaces\elements\CommissionRuleInterface;
use anvildev\craftkickback\gql\types\elements\CommissionRuleType;
use craft\gql\base\GeneratorInterface;
use craft\gql\GqlEntityRegistry;

class CommissionRuleTypeGenerator implements GeneratorInterface
{
    public static function generateTypes(mixed $context = null): array
    {
        $n = static::getName();
        return [$n => GqlEntityRegistry::getEntity($n) ?: GqlEntityRegistry::createEntity($n, new CommissionRuleType([
            'name' => $n,
            'fields' => CommissionRuleInterface::class . '::getFieldDefinitions',
        ]))];
    }

    public static function getName(mixed $context = null): string
    {
        return 'KickbackCommissionRule';
    }
}
