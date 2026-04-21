<?php

declare(strict_types=1);

namespace anvildev\craftkickback\gql\interfaces\elements;

use anvildev\craftkickback\gql\GqlSchemaHelper;
use anvildev\craftkickback\gql\types\generators\PayoutTypeGenerator;
use craft\gql\GqlEntityRegistry;
use craft\gql\interfaces\Element;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\Type;

class PayoutInterface extends Element
{
    public static function getTypeGenerator(): string
    {
        return PayoutTypeGenerator::class;
    }

    public static function getType(): Type
    {
        if ($type = GqlEntityRegistry::getEntity(self::getName())) {
            return $type;
        }

        $type = GqlEntityRegistry::createEntity(self::getName(), new InterfaceType([
            'name' => static::getName(),
            'fields' => self::class . '::getFieldDefinitions',
            'description' => 'This is the interface implemented by all Kickback payout elements.',
            'resolveType' => self::class . '::resolveElementTypeName',
        ]));
        PayoutTypeGenerator::generateTypes();

        return $type;
    }

    public static function getName(): string
    {
        return 'KickbackPayoutInterface';
    }

    public static function getFieldDefinitions(): array
    {
        return \Craft::$app->getGql()->prepareFieldDefinitions(array_merge(parent::getFieldDefinitions(), [
            'affiliateId' => [
                'name' => 'affiliateId',
                'type' => Type::int(),
                'description' => 'The ID of the affiliate this payout belongs to.',
            ],
            'createdByUserId' => [
                'name' => 'createdByUserId',
                'type' => Type::int(),
                'description' => 'The ID of the user who created this payout.',
            ],
            'amount' => GqlSchemaHelper::redactForPublic([
                'name' => 'amount',
                'type' => Type::float(),
                'description' => 'The payout amount. Redacted for public schema callers.',
            ]),
            'currency' => [
                'name' => 'currency',
                'type' => Type::string(),
                'description' => 'The ISO currency code for this payout.',
            ],
            'method' => GqlSchemaHelper::redactForPublic([
                'name' => 'method',
                'type' => Type::string(),
                'description' => 'The payout method (paypal, stripe, store_credit, or manual). Redacted for public schema callers.',
            ]),
            'payoutStatus' => GqlSchemaHelper::redactForPublic([
                'name' => 'payoutStatus',
                'type' => Type::string(),
                'description' => 'The payout\'s status (pending, processing, completed, failed, rejected, or reversed). Redacted for public schema callers.',
            ]),
            'transactionId' => GqlSchemaHelper::redactForPublic([
                'name' => 'transactionId',
                'type' => Type::string(),
                'description' => 'The gateway transaction reference for this payout, if available. Redacted for public schema callers.',
            ]),
            'gatewayBatchId' => GqlSchemaHelper::redactForPublic([
                'name' => 'gatewayBatchId',
                'type' => Type::string(),
                'description' => 'The gateway batch ID for this payout, if applicable. Redacted for public schema callers.',
            ]),
            'notes' => GqlSchemaHelper::redactForPublic([
                'name' => 'notes',
                'type' => Type::string(),
                'description' => 'Optional notes attached to this payout. Redacted for public schema callers.',
            ]),
            'processedAt' => [
                'name' => 'processedAt',
                'type' => Type::string(),
                'description' => 'The date/time this payout was processed, if applicable.',
            ],
        ]), self::getName());
    }
}
