<?php

declare(strict_types=1);

namespace anvildev\craftkickback\gql\interfaces\elements;

use anvildev\craftkickback\gql\GqlSchemaHelper;
use anvildev\craftkickback\gql\types\generators\AffiliateTypeGenerator;
use craft\gql\GqlEntityRegistry;
use craft\gql\interfaces\Element;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\Type;

class AffiliateInterface extends Element
{
    public static function getTypeGenerator(): string
    {
        return AffiliateTypeGenerator::class;
    }

    public static function getType(): Type
    {
        if ($type = GqlEntityRegistry::getEntity(self::getName())) {
            return $type;
        }

        $type = GqlEntityRegistry::createEntity(self::getName(), new InterfaceType([
            'name' => static::getName(),
            'fields' => self::class . '::getFieldDefinitions',
            'description' => 'This is the interface implemented by all Kickback affiliate elements.',
            'resolveType' => self::class . '::resolveElementTypeName',
        ]));
        AffiliateTypeGenerator::generateTypes();

        return $type;
    }

    public static function getName(): string
    {
        return 'KickbackAffiliateInterface';
    }

    public static function getFieldDefinitions(): array
    {
        return \Craft::$app->getGql()->prepareFieldDefinitions(array_merge(parent::getFieldDefinitions(), [
            'referralCode' => [
                'name' => 'referralCode',
                'type' => Type::string(),
                'description' => 'The affiliate\'s unique referral code.',
            ],
            'affiliateStatus' => [
                'name' => 'affiliateStatus',
                'type' => Type::string(),
                'description' => 'The affiliate\'s status (active, pending, suspended, rejected).',
            ],
            'programId' => [
                'name' => 'programId',
                'type' => Type::int(),
                'description' => 'The ID of the affiliate program this affiliate belongs to.',
            ],
            'pendingBalance' => GqlSchemaHelper::redactForPublic([
                'name' => 'pendingBalance',
                'type' => Type::float(),
                'description' => 'The affiliate\'s current pending balance awaiting payout. Redacted for public schema callers.',
            ]),
            'lifetimeEarnings' => GqlSchemaHelper::redactForPublic([
                'name' => 'lifetimeEarnings',
                'type' => Type::float(),
                'description' => 'The affiliate\'s total lifetime earnings. Redacted for public schema callers.',
            ]),
            'lifetimeReferrals' => [
                'name' => 'lifetimeReferrals',
                'type' => Type::int(),
                'description' => 'The total number of referrals attributed to this affiliate.',
            ],
            'paypalEmail' => GqlSchemaHelper::redactForPublic([
                'name' => 'paypalEmail',
                'type' => Type::string(),
                'description' => 'The affiliate\'s PayPal email address for payouts. Redacted for public schema callers.',
            ]),
            'payoutMethod' => GqlSchemaHelper::redactForPublic([
                'name' => 'payoutMethod',
                'type' => Type::string(),
                'description' => 'The affiliate\'s preferred payout method. Redacted for public schema callers.',
            ]),
        ]), self::getName());
    }
}
