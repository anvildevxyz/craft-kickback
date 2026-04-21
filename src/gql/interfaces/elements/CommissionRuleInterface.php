<?php

declare(strict_types=1);

namespace anvildev\craftkickback\gql\interfaces\elements;

use anvildev\craftkickback\gql\GqlSchemaHelper;
use anvildev\craftkickback\gql\types\generators\CommissionRuleTypeGenerator;
use craft\gql\GqlEntityRegistry;
use craft\gql\interfaces\Element;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\Type;

class CommissionRuleInterface extends Element
{
    public static function getTypeGenerator(): string
    {
        return CommissionRuleTypeGenerator::class;
    }

    public static function getType(): Type
    {
        if ($type = GqlEntityRegistry::getEntity(self::getName())) {
            return $type;
        }

        $type = GqlEntityRegistry::createEntity(self::getName(), new InterfaceType([
            'name' => static::getName(),
            'fields' => self::class . '::getFieldDefinitions',
            'description' => 'This is the interface implemented by all Kickback commission rule elements.',
            'resolveType' => self::class . '::resolveElementTypeName',
        ]));
        CommissionRuleTypeGenerator::generateTypes();

        return $type;
    }

    public static function getName(): string
    {
        return 'KickbackCommissionRuleInterface';
    }

    public static function getFieldDefinitions(): array
    {
        return \Craft::$app->getGql()->prepareFieldDefinitions(array_merge(parent::getFieldDefinitions(), [
            'programId' => [
                'name' => 'programId',
                'type' => Type::int(),
                'description' => 'The ID of the program this commission rule belongs to.',
            ],
            'name' => [
                'name' => 'name',
                'type' => Type::string(),
                'description' => 'The name of the commission rule.',
            ],
            'type' => [
                'name' => 'type',
                'type' => Type::string(),
                'description' => 'The rule type (product, category, tiered, bonus, or mlm_tier).',
            ],
            'targetId' => [
                'name' => 'targetId',
                'type' => Type::int(),
                'description' => 'The ID of the rule\'s target (e.g. product or category), if applicable.',
            ],
            'commissionRate' => GqlSchemaHelper::redactForPublic([
                'name' => 'commissionRate',
                'type' => Type::float(),
                'description' => 'The commission rate defined by this rule. Redacted for public schema callers.',
            ]),
            'commissionType' => GqlSchemaHelper::redactForPublic([
                'name' => 'commissionType',
                'type' => Type::string(),
                'description' => 'The commission rate type (percentage or flat). Redacted for public schema callers.',
            ]),
            'tierThreshold' => [
                'name' => 'tierThreshold',
                'type' => Type::int(),
                'description' => 'The minimum number of referrals required to unlock this tiered rate, if applicable.',
            ],
            'tierLevel' => [
                'name' => 'tierLevel',
                'type' => Type::int(),
                'description' => 'The MLM tier level this rule applies to, if applicable.',
            ],
            'lookbackDays' => [
                'name' => 'lookbackDays',
                'type' => Type::int(),
                'description' => 'The number of days to look back when evaluating the tier threshold, if applicable.',
            ],
            'priority' => [
                'name' => 'priority',
                'type' => Type::int(),
                'description' => 'The priority of this rule (higher = evaluated first).',
            ],
            'conditions' => [
                'name' => 'conditions',
                'type' => Type::string(),
                'description' => 'JSON-encoded conditions for this rule, if any.',
            ],
        ]), self::getName());
    }
}
