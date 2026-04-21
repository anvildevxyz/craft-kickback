<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Gql;

use anvildev\craftkickback\gql\interfaces\elements\AffiliateGroupInterface;
use anvildev\craftkickback\gql\interfaces\elements\AffiliateInterface;
use anvildev\craftkickback\gql\interfaces\elements\CommissionInterface;
use anvildev\craftkickback\gql\interfaces\elements\CommissionRuleInterface;
use anvildev\craftkickback\gql\interfaces\elements\PayoutInterface;
use anvildev\craftkickback\gql\interfaces\elements\ProgramInterface;
use anvildev\craftkickback\gql\interfaces\elements\ReferralInterface;
use anvildev\craftkickback\gql\queries\KickbackQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SchemaSmokeTest extends TestCase
{
    #[Test]
    public function graphqlInterfacesAndRootQueryAreRegisteredInCodebase(): void
    {
        foreach ([
            AffiliateInterface::class,
            AffiliateGroupInterface::class,
            ProgramInterface::class,
            CommissionRuleInterface::class,
            ReferralInterface::class,
            CommissionInterface::class,
            PayoutInterface::class,
            KickbackQuery::class,
        ] as $className) {
            self::assertTrue(
                class_exists($className),
                "{$className} should exist for Kickback GraphQL schema registration.",
            );
        }
    }
}

