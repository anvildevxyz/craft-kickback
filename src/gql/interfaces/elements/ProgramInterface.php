<?php

declare(strict_types=1);

namespace anvildev\craftkickback\gql\interfaces\elements;

use anvildev\craftkickback\gql\GqlSchemaHelper;
use anvildev\craftkickback\gql\types\generators\ProgramTypeGenerator;
use craft\gql\GqlEntityRegistry;
use craft\gql\interfaces\Element;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\Type;

class ProgramInterface extends Element
{
    public static function getTypeGenerator(): string
    {
        return ProgramTypeGenerator::class;
    }

    public static function getType(): Type
    {
        if ($type = GqlEntityRegistry::getEntity(self::getName())) {
            return $type;
        }

        $type = GqlEntityRegistry::createEntity(self::getName(), new InterfaceType([
            'name' => static::getName(),
            'fields' => self::class . '::getFieldDefinitions',
            'description' => 'This is the interface implemented by all Kickback program elements.',
            'resolveType' => self::class . '::resolveElementTypeName',
        ]));
        ProgramTypeGenerator::generateTypes();

        return $type;
    }

    public static function getName(): string
    {
        return 'KickbackProgramInterface';
    }

    public static function getFieldDefinitions(): array
    {
        return \Craft::$app->getGql()->prepareFieldDefinitions(array_merge(parent::getFieldDefinitions(), [
            'handle' => [
                'name' => 'handle',
                'type' => Type::string(),
                'description' => 'The program\'s unique handle.',
            ],
            'defaultCommissionRate' => GqlSchemaHelper::redactForPublic([
                'name' => 'defaultCommissionRate',
                'type' => Type::float(),
                'description' => 'The program\'s default commission rate. Redacted for public schema callers.',
            ]),
            'defaultCommissionType' => GqlSchemaHelper::redactForPublic([
                'name' => 'defaultCommissionType',
                'type' => Type::string(),
                'description' => 'The program\'s default commission type (percentage or flat). Redacted for public schema callers.',
            ]),
            'cookieDuration' => [
                'name' => 'cookieDuration',
                'type' => Type::int(),
                'description' => 'The number of days the referral tracking cookie persists.',
            ],
            'allowSelfReferral' => [
                'name' => 'allowSelfReferral',
                'type' => Type::boolean(),
                'description' => 'Whether self-referrals are permitted in this program.',
            ],
            'programStatus' => [
                'name' => 'programStatus',
                'type' => Type::string(),
                'description' => 'The program\'s status (active, inactive, or archived).',
            ],
        ]), self::getName());
    }
}
