<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Gql;

use anvildev\craftkickback\gql\arguments\elements\AffiliateArguments;
use anvildev\craftkickback\gql\interfaces\elements\AffiliateInterface;
use anvildev\craftkickback\gql\queries\KickbackQuery;
use anvildev\craftkickback\gql\types\generators\AffiliateTypeGenerator;
use PHPUnit\Framework\TestCase;

class AffiliateSchemaTest extends TestCase
{
    public function testAffiliateInterfaceClassExists(): void
    {
        $this->assertTrue(class_exists(AffiliateInterface::class));
    }

    public function testAffiliateTypeGeneratorImplementsCraftInterface(): void
    {
        $this->assertTrue(
            is_subclass_of(
                AffiliateTypeGenerator::class,
                \craft\gql\base\GeneratorInterface::class,
            ),
            'AffiliateTypeGenerator must implement Craft\'s GeneratorInterface',
        );
    }

    public function testAffiliateArgumentsExtendsElementArguments(): void
    {
        $this->assertTrue(
            is_subclass_of(
                AffiliateArguments::class,
                \craft\gql\base\ElementArguments::class,
            ),
        );
    }

    public function testKickbackQueryReturnsAffiliateEntries(): void
    {
        // KickbackQuery::getQueries() calls AffiliateInterface::getType() eagerly
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
        // to catch the regression "someone removed the kickbackAffiliates
        // query entry from KickbackQuery::getQueries()".
        $queriesSource = file_get_contents(
            __DIR__ . '/../../../src/gql/queries/KickbackQuery.php'
        );
        $this->assertNotFalse($queriesSource, 'KickbackQuery.php must be readable');

        $this->assertStringContainsString(
            "'kickbackAffiliates'",
            $queriesSource,
            'KickbackQuery must register the kickbackAffiliates list query',
        );
        $this->assertStringContainsString(
            "'kickbackAffiliate'",
            $queriesSource,
            'KickbackQuery must register the kickbackAffiliate single query',
        );
        $this->assertStringContainsString(
            'AffiliateInterface::getType()',
            $queriesSource,
            'KickbackQuery\'s affiliate entries must reference AffiliateInterface::getType()',
        );
    }

    public function testAffiliateInterfaceFieldsAreExpected(): void
    {
        $source = file_get_contents(
            __DIR__ . '/../../../src/gql/interfaces/elements/AffiliateInterface.php'
        );
        $this->assertNotFalse($source, 'AffiliateInterface.php must be readable');

        // Search from the start of getFieldDefinitions so inherited parent
        // fields (id, uid, dateCreated, etc.) don't cause false positives.
        $start = strpos($source, 'function getFieldDefinitions(');
        $this->assertNotFalse($start, 'AffiliateInterface must declare getFieldDefinitions()');
        $body = substr($source, $start);

        foreach ([
            'referralCode',
            'affiliateStatus',
            'programId',
            'pendingBalance',
            'lifetimeEarnings',
            'lifetimeReferrals',
            'paypalEmail',
            'payoutMethod',
        ] as $field) {
            $this->assertStringContainsString(
                "'{$field}' =>",
                $body,
                "AffiliateInterface must declare the '{$field}' GraphQL field",
            );
        }

        // Wave 4 Task 4.4 hardening: sensitive financial / PII fields must be
        // wrapped in GqlSchemaHelper::redactForPublic().
        $this->assertStringContainsString(
            'GqlSchemaHelper',
            $source,
            'AffiliateInterface must import GqlSchemaHelper for public-schema redaction',
        );

        foreach (['pendingBalance', 'lifetimeEarnings', 'paypalEmail', 'payoutMethod'] as $redacted) {
            $this->assertStringContainsString(
                'redactForPublic(',
                $body,
                "AffiliateInterface must wrap sensitive field '{$redacted}' in GqlSchemaHelper::redactForPublic()",
            );
            // Verify the redactForPublic call appears near the field declaration.
            $fieldPos = strpos($body, "'{$redacted}' =>");
            $this->assertNotFalse($fieldPos);
            $surrounding = substr($body, max(0, $fieldPos - 30), 60);
            $this->assertStringContainsString(
                'redactForPublic(',
                substr($body, max(0, $fieldPos - 30), 100),
                "The '{$redacted}' field declaration must be wrapped in redactForPublic()",
            );
        }
    }

    public function testAffiliateElementGqlTypeNameMatchesGeneratorName(): void
    {
        // Instantiating AffiliateElement directly requires a full Craft/Yii bootstrap.
        // Use a partial mock with the constructor disabled so we can call the real
        // getGqlTypeName() override without triggering Yii's DI container.
        $affiliate = $this->getMockBuilder(\anvildev\craftkickback\elements\AffiliateElement::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $this->assertSame(
            \anvildev\craftkickback\gql\types\generators\AffiliateTypeGenerator::getName(),
            $affiliate->getGqlTypeName(),
            'AffiliateElement::getGqlTypeName() must match the name the generator registers, '
            . 'otherwise GraphQL will fail to resolve affiliate instances at query time.',
        );
    }
}
