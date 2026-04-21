<?php

declare(strict_types=1);

namespace anvildev\craftkickback\gql\queries;

use anvildev\craftkickback\elements\AffiliateElement;
use anvildev\craftkickback\elements\AffiliateGroupElement;
use anvildev\craftkickback\elements\CommissionElement;
use anvildev\craftkickback\elements\CommissionRuleElement;
use anvildev\craftkickback\elements\PayoutElement;
use anvildev\craftkickback\elements\ProgramElement;
use anvildev\craftkickback\elements\ReferralElement;
use anvildev\craftkickback\gql\arguments\elements\AffiliateArguments;
use anvildev\craftkickback\gql\arguments\elements\AffiliateGroupArguments;
use anvildev\craftkickback\gql\arguments\elements\CommissionArguments;
use anvildev\craftkickback\gql\arguments\elements\CommissionRuleArguments;
use anvildev\craftkickback\gql\arguments\elements\PayoutArguments;
use anvildev\craftkickback\gql\arguments\elements\ProgramArguments;
use anvildev\craftkickback\gql\arguments\elements\ReferralArguments;
use anvildev\craftkickback\gql\GqlSchemaHelper;
use anvildev\craftkickback\gql\interfaces\elements\AffiliateGroupInterface;
use anvildev\craftkickback\gql\interfaces\elements\AffiliateInterface;
use anvildev\craftkickback\gql\interfaces\elements\CommissionInterface;
use anvildev\craftkickback\gql\interfaces\elements\CommissionRuleInterface;
use anvildev\craftkickback\gql\interfaces\elements\PayoutInterface;
use anvildev\craftkickback\gql\interfaces\elements\ProgramInterface;
use anvildev\craftkickback\gql\interfaces\elements\ReferralInterface;
use craft\elements\db\ElementQuery;
use craft\gql\base\Query;
use GraphQL\Type\Definition\Type;

class KickbackQuery extends Query
{
    public static function getQueries(bool $checkToken = true): array
    {
        return [
            'kickbackAffiliates' => [
                'type' => Type::listOf(AffiliateInterface::getType()),
                'args' => AffiliateArguments::getArguments(),
                'resolve' => function($source, array $arguments) {
                    /** @var \anvildev\craftkickback\elements\db\AffiliateQuery $query */
                    $query = self::applyArgs(AffiliateElement::find(), $arguments);
                    // Public schema is never allowed to see inactive affiliates.
                    if (GqlSchemaHelper::isPublicSchema()) {
                        $query->affiliateStatus(AffiliateElement::STATUS_ACTIVE);
                    }
                    return $query->all();
                },
                'description' => 'Returns a list of Kickback affiliate elements.',
            ],
            'kickbackAffiliate' => [
                'type' => AffiliateInterface::getType(),
                'args' => AffiliateArguments::getArguments(),
                'resolve' => fn($source, array $arguments) =>
                    self::applyArgs(AffiliateElement::find(), $arguments)->one(),
                'description' => 'Returns a single Kickback affiliate element.',
            ],
            'kickbackPrograms' => [
                'type' => Type::listOf(ProgramInterface::getType()),
                'args' => ProgramArguments::getArguments(),
                'resolve' => fn($source, array $arguments) =>
                    self::applyArgs(ProgramElement::find(), $arguments)->all(),
                'description' => 'Query Kickback programs.',
            ],
            'kickbackProgram' => [
                'type' => ProgramInterface::getType(),
                'args' => ProgramArguments::getArguments(),
                'resolve' => fn($source, array $arguments) =>
                    self::applyArgs(ProgramElement::find(), $arguments)->one(),
                'description' => 'Query a single Kickback program.',
            ],
            'kickbackReferrals' => [
                'type' => Type::listOf(ReferralInterface::getType()),
                'args' => ReferralArguments::getArguments(),
                'resolve' => function($source, array $arguments) {
                    // Public schema must not expose referrals.
                    if (GqlSchemaHelper::isPublicSchema()) {
                        return [];
                    }
                    return self::applyArgs(ReferralElement::find(), $arguments)->all();
                },
                'description' => 'Query Kickback referrals.',
            ],
            'kickbackReferral' => [
                'type' => ReferralInterface::getType(),
                'args' => ReferralArguments::getArguments(),
                'resolve' => function($source, array $arguments) {
                    if (GqlSchemaHelper::isPublicSchema()) {
                        return null;
                    }
                    return self::applyArgs(ReferralElement::find(), $arguments)->one();
                },
                'description' => 'Query a single Kickback referral.',
            ],
            'kickbackCommissions' => [
                'type' => Type::listOf(CommissionInterface::getType()),
                'args' => CommissionArguments::getArguments(),
                'resolve' => function($source, array $arguments) {
                    // Public schema must not expose commissions.
                    if (GqlSchemaHelper::isPublicSchema()) {
                        return [];
                    }
                    return self::applyArgs(CommissionElement::find(), $arguments)->all();
                },
                'description' => 'Query Kickback commissions.',
            ],
            'kickbackCommission' => [
                'type' => CommissionInterface::getType(),
                'args' => CommissionArguments::getArguments(),
                'resolve' => function($source, array $arguments) {
                    if (GqlSchemaHelper::isPublicSchema()) {
                        return null;
                    }
                    return self::applyArgs(CommissionElement::find(), $arguments)->one();
                },
                'description' => 'Query a single Kickback commission.',
            ],
            'kickbackPayouts' => [
                'type' => Type::listOf(PayoutInterface::getType()),
                'args' => PayoutArguments::getArguments(),
                'resolve' => function($source, array $arguments) {
                    // Public schema must not expose payouts.
                    if (GqlSchemaHelper::isPublicSchema()) {
                        return [];
                    }
                    return self::applyArgs(PayoutElement::find(), $arguments)->all();
                },
                'description' => 'Query Kickback payouts.',
            ],
            'kickbackPayout' => [
                'type' => PayoutInterface::getType(),
                'args' => PayoutArguments::getArguments(),
                'resolve' => function($source, array $arguments) {
                    if (GqlSchemaHelper::isPublicSchema()) {
                        return null;
                    }
                    return self::applyArgs(PayoutElement::find(), $arguments)->one();
                },
                'description' => 'Query a single Kickback payout.',
            ],
            'kickbackAffiliateGroups' => [
                'type' => Type::listOf(AffiliateGroupInterface::getType()),
                'args' => AffiliateGroupArguments::getArguments(),
                'resolve' => fn($source, array $arguments) =>
                    self::applyArgs(AffiliateGroupElement::find(), $arguments)->all(),
                'description' => 'Query Kickback affiliate groups.',
            ],
            'kickbackAffiliateGroup' => [
                'type' => AffiliateGroupInterface::getType(),
                'args' => AffiliateGroupArguments::getArguments(),
                'resolve' => fn($source, array $arguments) =>
                    self::applyArgs(AffiliateGroupElement::find(), $arguments)->one(),
                'description' => 'Query a single Kickback affiliate group.',
            ],
            'kickbackCommissionRules' => [
                'type' => Type::listOf(CommissionRuleInterface::getType()),
                'args' => CommissionRuleArguments::getArguments(),
                'resolve' => fn($source, array $arguments) =>
                    self::applyArgs(CommissionRuleElement::find(), $arguments)->all(),
                'description' => 'Query Kickback commission rules.',
            ],
            'kickbackCommissionRule' => [
                'type' => CommissionRuleInterface::getType(),
                'args' => CommissionRuleArguments::getArguments(),
                'resolve' => fn($source, array $arguments) =>
                    self::applyArgs(CommissionRuleElement::find(), $arguments)->one(),
                'description' => 'Query a single Kickback commission rule.',
            ],
        ];
    }

    private static function applyArgs(ElementQuery $query, array $arguments): ElementQuery
    {
        foreach ($arguments as $key => $value) {
            if (method_exists($query, $key)) {
                $query->$key($value);
            }
        }
        return $query;
    }
}
