<?php

declare(strict_types=1);

namespace anvildev\craftkickback\gql\interfaces\elements;

use anvildev\craftkickback\gql\GqlSchemaHelper;
use anvildev\craftkickback\gql\types\generators\CommissionTypeGenerator;
use craft\gql\GqlEntityRegistry;
use craft\gql\interfaces\Element;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\Type;

class CommissionInterface extends Element
{
    public static function getTypeGenerator(): string
    {
        return CommissionTypeGenerator::class;
    }

    public static function getType(): Type
    {
        if ($type = GqlEntityRegistry::getEntity(self::getName())) {
            return $type;
        }

        $type = GqlEntityRegistry::createEntity(self::getName(), new InterfaceType([
            'name' => static::getName(),
            'fields' => self::class . '::getFieldDefinitions',
            'description' => 'This is the interface implemented by all Kickback commission elements.',
            'resolveType' => self::class . '::resolveElementTypeName',
        ]));
        CommissionTypeGenerator::generateTypes();

        return $type;
    }

    public static function getName(): string
    {
        return 'KickbackCommissionInterface';
    }

    public static function getFieldDefinitions(): array
    {
        return \Craft::$app->getGql()->prepareFieldDefinitions(array_merge(parent::getFieldDefinitions(), [
            'affiliateId' => [
                'name' => 'affiliateId',
                'type' => Type::int(),
                'description' => 'The ID of the affiliate who earned this commission.',
            ],
            'referralId' => [
                'name' => 'referralId',
                'type' => Type::int(),
                'description' => 'The ID of the referral that generated this commission.',
            ],
            'amount' => GqlSchemaHelper::redactForPublic([
                'name' => 'amount',
                'type' => Type::float(),
                'description' => 'The current commission amount (may be reduced by refunds). Redacted for public schema callers.',
            ]),
            'originalAmount' => GqlSchemaHelper::redactForPublic([
                'name' => 'originalAmount',
                'type' => Type::float(),
                'description' => 'The original commission amount at the time it was created (immutable snapshot). Redacted for public schema callers.',
            ]),
            'currency' => [
                'name' => 'currency',
                'type' => Type::string(),
                'description' => 'The ISO currency code for this commission.',
            ],
            'rate' => GqlSchemaHelper::redactForPublic([
                'name' => 'rate',
                'type' => Type::float(),
                'description' => 'The commission rate applied. Redacted for public schema callers.',
            ]),
            'rateType' => GqlSchemaHelper::redactForPublic([
                'name' => 'rateType',
                'type' => Type::string(),
                'description' => 'The type of commission rate (percentage or flat). Redacted for public schema callers.',
            ]),
            'tier' => [
                'name' => 'tier',
                'type' => Type::int(),
                'description' => 'The tier level of this commission (1 = direct referral).',
            ],
            'commissionStatus' => GqlSchemaHelper::redactForPublic([
                'name' => 'commissionStatus',
                'type' => Type::string(),
                'description' => 'The commission\'s status (pending, approved, paid, reversed, or rejected). Redacted for public schema callers.',
            ]),
            'payoutId' => [
                'name' => 'payoutId',
                'type' => Type::int(),
                'description' => 'The ID of the payout that included this commission, if paid.',
            ],
            'ruleApplied' => [
                'name' => 'ruleApplied',
                'type' => Type::string(),
                'description' => 'The handle of the commission rule that determined this commission, if any.',
            ],
        ]), self::getName());
    }
}
