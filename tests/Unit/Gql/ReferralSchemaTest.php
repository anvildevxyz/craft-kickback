<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Gql;

use anvildev\craftkickback\gql\arguments\elements\ReferralArguments;
use anvildev\craftkickback\gql\interfaces\elements\ReferralInterface;
use anvildev\craftkickback\gql\queries\KickbackQuery;
use anvildev\craftkickback\gql\types\generators\ReferralTypeGenerator;
use PHPUnit\Framework\TestCase;

class ReferralSchemaTest extends TestCase
{
    public function testReferralInterfaceClassExists(): void
    {
        $this->assertTrue(class_exists(ReferralInterface::class));
    }

    public function testReferralTypeGeneratorImplementsCraftInterface(): void
    {
        $this->assertTrue(
            is_subclass_of(
                ReferralTypeGenerator::class,
                \craft\gql\base\GeneratorInterface::class,
            ),
            'ReferralTypeGenerator must implement Craft\'s GeneratorInterface',
        );
    }

    public function testReferralArgumentsExtendsElementArguments(): void
    {
        $this->assertTrue(
            is_subclass_of(
                ReferralArguments::class,
                \craft\gql\base\ElementArguments::class,
            ),
        );
    }

    public function testKickbackQueryReturnsReferralEntries(): void
    {
        // KickbackQuery::getQueries() calls ReferralInterface::getType() eagerly
        // (building the GraphQL type from GqlEntityRegistry), which requires a full
        // Craft + GQL bootstrap. We verify the class shape instead.
        $this->assertTrue(
            is_subclass_of(KickbackQuery::class, \craft\gql\base\Query::class),
            'KickbackQuery must extend Craft\'s base Query class',
        );
        $this->assertTrue(
            method_exists(KickbackQuery::class, 'getQueries'),
            'KickbackQuery must implement getQueries()',
        );
        // Source-inspect KickbackQuery for the query entries this test owns.
        // We can't call getQueries() at runtime because it touches
        // GqlEntityRegistry which needs a Craft bootstrap. Grepping the
        // source for the query keys + the interface reference is enough
        // to catch the regression "someone removed the kickbackReferrals
        // query entry from KickbackQuery::getQueries()".
        $queriesSource = file_get_contents(
            __DIR__ . '/../../../src/gql/queries/KickbackQuery.php'
        );
        $this->assertNotFalse($queriesSource, 'KickbackQuery.php must be readable');

        $this->assertStringContainsString(
            "'kickbackReferrals'",
            $queriesSource,
            'KickbackQuery must register the kickbackReferrals list query',
        );
        $this->assertStringContainsString(
            "'kickbackReferral'",
            $queriesSource,
            'KickbackQuery must register the kickbackReferral single query',
        );
        $this->assertStringContainsString(
            'ReferralInterface::getType()',
            $queriesSource,
            'KickbackQuery\'s referral entries must reference ReferralInterface::getType()',
        );
    }

    public function testReferralInterfaceFieldsAreExpected(): void
    {
        $source = file_get_contents(
            __DIR__ . '/../../../src/gql/interfaces/elements/ReferralInterface.php'
        );
        $this->assertNotFalse($source, 'ReferralInterface.php must be readable');

        $start = strpos($source, 'function getFieldDefinitions(');
        $this->assertNotFalse($start, 'ReferralInterface must declare getFieldDefinitions()');
        $body = substr($source, $start);

        foreach ([
            'affiliateId',
            'programId',
            'orderId',
            'customerEmail',
            'orderSubtotal',
            'referralStatus',
            'attributionMethod',
            'couponCode',
        ] as $field) {
            $this->assertStringContainsString(
                "'{$field}' =>",
                $body,
                "ReferralInterface must declare the '{$field}' GraphQL field",
            );
        }

        // Wave 4 Task 4.4: PII and financial fields are redacted from public callers.
        $this->assertStringContainsString(
            'GqlSchemaHelper',
            $source,
            'ReferralInterface must import GqlSchemaHelper for public-schema redaction',
        );

        foreach (['customerEmail', 'orderSubtotal', 'referralStatus'] as $redacted) {
            $fieldPos = strpos($body, "'{$redacted}' =>");
            $this->assertNotFalse($fieldPos, "Field '{$redacted}' must exist in getFieldDefinitions body");
            $this->assertStringContainsString(
                'redactForPublic(',
                substr($body, max(0, $fieldPos - 30), 100),
                "The '{$redacted}' field declaration must be wrapped in redactForPublic()",
            );
        }
    }

    public function testReferralElementGqlTypeNameMatchesGeneratorName(): void
    {
        // Instantiating ReferralElement directly requires a full Craft/Yii bootstrap.
        // Use a partial mock with the constructor disabled so we can call the real
        // getGqlTypeName() override without triggering Yii's DI container.
        $referral = $this->getMockBuilder(\anvildev\craftkickback\elements\ReferralElement::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $this->assertSame(
            \anvildev\craftkickback\gql\types\generators\ReferralTypeGenerator::getName(),
            $referral->getGqlTypeName(),
            'ReferralElement::getGqlTypeName() must match the name the generator registers, '
            . 'otherwise GraphQL will fail to resolve referral instances at query time.',
        );
    }
}
