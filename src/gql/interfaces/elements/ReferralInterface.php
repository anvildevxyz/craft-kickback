<?php

declare(strict_types=1);

namespace anvildev\craftkickback\gql\interfaces\elements;

use anvildev\craftkickback\gql\GqlSchemaHelper;
use anvildev\craftkickback\gql\types\generators\ReferralTypeGenerator;
use craft\gql\GqlEntityRegistry;
use craft\gql\interfaces\Element;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\Type;

class ReferralInterface extends Element
{
    public static function getTypeGenerator(): string
    {
        return ReferralTypeGenerator::class;
    }

    public static function getType(): Type
    {
        if ($type = GqlEntityRegistry::getEntity(self::getName())) {
            return $type;
        }

        $type = GqlEntityRegistry::createEntity(self::getName(), new InterfaceType([
            'name' => static::getName(),
            'fields' => self::class . '::getFieldDefinitions',
            'description' => 'This is the interface implemented by all Kickback referral elements.',
            'resolveType' => self::class . '::resolveElementTypeName',
        ]));
        ReferralTypeGenerator::generateTypes();

        return $type;
    }

    public static function getName(): string
    {
        return 'KickbackReferralInterface';
    }

    public static function getFieldDefinitions(): array
    {
        return \Craft::$app->getGql()->prepareFieldDefinitions(array_merge(parent::getFieldDefinitions(), [
            'affiliateId' => [
                'name' => 'affiliateId',
                'type' => Type::int(),
                'description' => 'The ID of the affiliate who generated this referral.',
            ],
            'programId' => [
                'name' => 'programId',
                'type' => Type::int(),
                'description' => 'The ID of the affiliate program this referral belongs to.',
            ],
            'orderId' => [
                'name' => 'orderId',
                'type' => Type::int(),
                'description' => 'The ID of the Commerce order attributed to this referral.',
            ],
            'customerEmail' => GqlSchemaHelper::redactForPublic([
                'name' => 'customerEmail',
                'type' => Type::string(),
                'description' => 'The email address of the referred customer. PII - redacted for public schema callers.',
            ]),
            'orderSubtotal' => GqlSchemaHelper::redactForPublic([
                'name' => 'orderSubtotal',
                'type' => Type::float(),
                'description' => 'The subtotal of the referred order. Redacted for public schema callers.',
            ]),
            'referralStatus' => GqlSchemaHelper::redactForPublic([
                'name' => 'referralStatus',
                'type' => Type::string(),
                'description' => 'The referral\'s status (pending, approved, flagged, rejected, or paid). Redacted for public schema callers.',
            ]),
            'attributionMethod' => [
                'name' => 'attributionMethod',
                'type' => Type::string(),
                'description' => 'How this referral was attributed (cookie, coupon, direct_link, lifetime_customer, or manual).',
            ],
            'couponCode' => [
                'name' => 'couponCode',
                'type' => Type::string(),
                'description' => 'The coupon code used to attribute this referral, if applicable.',
            ],
            'subId' => [
                'name' => 'subId',
                'type' => Type::string(),
                'description' => 'Optional campaign sub-identifier carried from the tracking click through to the referral.',
            ],
        ]), self::getName());
    }
}
