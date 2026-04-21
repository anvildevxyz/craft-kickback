<?php

declare(strict_types=1);

namespace anvildev\craftkickback\gql\interfaces\elements;

use anvildev\craftkickback\gql\GqlSchemaHelper;
use anvildev\craftkickback\gql\types\generators\AffiliateGroupTypeGenerator;
use craft\gql\GqlEntityRegistry;
use craft\gql\interfaces\Element;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\Type;

class AffiliateGroupInterface extends Element
{
    public static function getTypeGenerator(): string
    {
        return AffiliateGroupTypeGenerator::class;
    }

    public static function getType(): Type
    {
        if ($type = GqlEntityRegistry::getEntity(self::getName())) {
            return $type;
        }

        $type = GqlEntityRegistry::createEntity(self::getName(), new InterfaceType([
            'name' => static::getName(),
            'fields' => self::class . '::getFieldDefinitions',
            'description' => 'This is the interface implemented by all Kickback affiliate group elements.',
            'resolveType' => self::class . '::resolveElementTypeName',
        ]));
        AffiliateGroupTypeGenerator::generateTypes();

        return $type;
    }

    public static function getName(): string
    {
        return 'KickbackAffiliateGroupInterface';
    }

    public static function getFieldDefinitions(): array
    {
        return \Craft::$app->getGql()->prepareFieldDefinitions(array_merge(parent::getFieldDefinitions(), [
            'name' => [
                'name' => 'name',
                'type' => Type::string(),
                'description' => 'The name of the affiliate group.',
            ],
            'handle' => [
                'name' => 'handle',
                'type' => Type::string(),
                'description' => 'The unique handle for this affiliate group.',
            ],
            'commissionRate' => GqlSchemaHelper::redactForPublic([
                'name' => 'commissionRate',
                'type' => Type::float(),
                'description' => 'The commission rate applied to affiliates in this group. Redacted for public schema callers.',
            ]),
            'commissionType' => GqlSchemaHelper::redactForPublic([
                'name' => 'commissionType',
                'type' => Type::string(),
                'description' => 'The commission rate type (percentage or flat). Redacted for public schema callers.',
            ]),
            'sortOrder' => [
                'name' => 'sortOrder',
                'type' => Type::int(),
                'description' => 'The sort order for this affiliate group.',
            ],
        ]), self::getName());
    }
}
