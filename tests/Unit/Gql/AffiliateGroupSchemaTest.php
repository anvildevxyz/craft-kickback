<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Gql;

use anvildev\craftkickback\gql\arguments\elements\AffiliateGroupArguments;
use anvildev\craftkickback\gql\interfaces\elements\AffiliateGroupInterface;
use anvildev\craftkickback\gql\queries\KickbackQuery;
use anvildev\craftkickback\gql\types\generators\AffiliateGroupTypeGenerator;
use PHPUnit\Framework\TestCase;

class AffiliateGroupSchemaTest extends TestCase
{
    public function testAffiliateGroupInterfaceClassExists(): void
    {
        $this->assertTrue(class_exists(AffiliateGroupInterface::class));
    }

    public function testAffiliateGroupTypeGeneratorImplementsCraftInterface(): void
    {
        $this->assertTrue(
            is_subclass_of(
                AffiliateGroupTypeGenerator::class,
                \craft\gql\base\GeneratorInterface::class,
            ),
            'AffiliateGroupTypeGenerator must implement Craft\'s GeneratorInterface',
        );
    }

    public function testAffiliateGroupArgumentsExtendsElementArguments(): void
    {
        $this->assertTrue(
            is_subclass_of(
                AffiliateGroupArguments::class,
                \craft\gql\base\ElementArguments::class,
            ),
        );
    }

    public function testKickbackQueryReturnsAffiliateGroupEntries(): void
    {
        // KickbackQuery::getQueries() calls AffiliateGroupInterface::getType() eagerly
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
        // to catch the regression "someone removed the kickbackAffiliateGroups
        // query entry from KickbackQuery::getQueries()".
        $queriesSource = file_get_contents(
            __DIR__ . '/../../../src/gql/queries/KickbackQuery.php'
        );
        $this->assertNotFalse($queriesSource, 'KickbackQuery.php must be readable');

        $this->assertStringContainsString(
            "'kickbackAffiliateGroups'",
            $queriesSource,
            'KickbackQuery must register the kickbackAffiliateGroups list query',
        );
        $this->assertStringContainsString(
            "'kickbackAffiliateGroup'",
            $queriesSource,
            'KickbackQuery must register the kickbackAffiliateGroup single query',
        );
        $this->assertStringContainsString(
            'AffiliateGroupInterface::getType()',
            $queriesSource,
            'KickbackQuery\'s affiliate group entries must reference AffiliateGroupInterface::getType()',
        );
    }

    public function testAffiliateGroupInterfaceFieldsAreExpected(): void
    {
        $source = file_get_contents(
            __DIR__ . '/../../../src/gql/interfaces/elements/AffiliateGroupInterface.php'
        );
        $this->assertNotFalse($source, 'AffiliateGroupInterface.php must be readable');

        $start = strpos($source, 'function getFieldDefinitions(');
        $this->assertNotFalse($start, 'AffiliateGroupInterface must declare getFieldDefinitions()');
        $body = substr($source, $start);

        foreach ([
            'name',
            'handle',
            'commissionRate',
            'commissionType',
            'sortOrder',
        ] as $field) {
            $this->assertStringContainsString(
                "'{$field}' =>",
                $body,
                "AffiliateGroupInterface must declare the '{$field}' GraphQL field",
            );
        }

        // Wave 4 Task 4.4: commission rate/type are redacted from public callers.
        $this->assertStringContainsString(
            'GqlSchemaHelper',
            $source,
            'AffiliateGroupInterface must import GqlSchemaHelper for public-schema redaction',
        );

        foreach (['commissionRate', 'commissionType'] as $redacted) {
            $fieldPos = strpos($body, "'{$redacted}' =>");
            $this->assertNotFalse($fieldPos, "Field '{$redacted}' must exist in getFieldDefinitions body");
            $this->assertStringContainsString(
                'redactForPublic(',
                substr($body, max(0, $fieldPos - 30), 100),
                "The '{$redacted}' field declaration must be wrapped in redactForPublic()",
            );
        }
    }

    public function testAffiliateGroupElementGqlTypeNameMatchesGeneratorName(): void
    {
        // Instantiating AffiliateGroupElement directly requires a full Craft/Yii bootstrap.
        // Use a partial mock with the constructor disabled so we can call the real
        // getGqlTypeName() override without triggering Yii's DI container.
        $group = $this->getMockBuilder(\anvildev\craftkickback\elements\AffiliateGroupElement::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $this->assertSame(
            \anvildev\craftkickback\gql\types\generators\AffiliateGroupTypeGenerator::getName(),
            $group->getGqlTypeName(),
            'AffiliateGroupElement::getGqlTypeName() must match the name the generator registers, '
            . 'otherwise GraphQL will fail to resolve affiliate group instances at query time.',
        );
    }
}
