<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Gql;

use anvildev\craftkickback\gql\arguments\elements\CommissionRuleArguments;
use anvildev\craftkickback\gql\interfaces\elements\CommissionRuleInterface;
use anvildev\craftkickback\gql\queries\KickbackQuery;
use anvildev\craftkickback\gql\types\generators\CommissionRuleTypeGenerator;
use PHPUnit\Framework\TestCase;

class CommissionRuleSchemaTest extends TestCase
{
    public function testCommissionRuleInterfaceClassExists(): void
    {
        $this->assertTrue(class_exists(CommissionRuleInterface::class));
    }

    public function testCommissionRuleTypeGeneratorImplementsCraftInterface(): void
    {
        $this->assertTrue(
            is_subclass_of(
                CommissionRuleTypeGenerator::class,
                \craft\gql\base\GeneratorInterface::class,
            ),
            'CommissionRuleTypeGenerator must implement Craft\'s GeneratorInterface',
        );
    }

    public function testCommissionRuleArgumentsExtendsElementArguments(): void
    {
        $this->assertTrue(
            is_subclass_of(
                CommissionRuleArguments::class,
                \craft\gql\base\ElementArguments::class,
            ),
        );
    }

    public function testKickbackQueryReturnsCommissionRuleEntries(): void
    {
        // KickbackQuery::getQueries() calls CommissionRuleInterface::getType() eagerly
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
        // to catch the regression "someone removed the kickbackCommissionRules
        // query entry from KickbackQuery::getQueries()".
        $queriesSource = file_get_contents(
            __DIR__ . '/../../../src/gql/queries/KickbackQuery.php'
        );
        $this->assertNotFalse($queriesSource, 'KickbackQuery.php must be readable');

        $this->assertStringContainsString(
            "'kickbackCommissionRules'",
            $queriesSource,
            'KickbackQuery must register the kickbackCommissionRules list query',
        );
        $this->assertStringContainsString(
            "'kickbackCommissionRule'",
            $queriesSource,
            'KickbackQuery must register the kickbackCommissionRule single query',
        );
        $this->assertStringContainsString(
            'CommissionRuleInterface::getType()',
            $queriesSource,
            'KickbackQuery\'s commission rule entries must reference CommissionRuleInterface::getType()',
        );
    }

    public function testCommissionRuleInterfaceFieldsAreExpected(): void
    {
        $source = file_get_contents(
            __DIR__ . '/../../../src/gql/interfaces/elements/CommissionRuleInterface.php'
        );
        $this->assertNotFalse($source, 'CommissionRuleInterface.php must be readable');

        $start = strpos($source, 'function getFieldDefinitions(');
        $this->assertNotFalse($start, 'CommissionRuleInterface must declare getFieldDefinitions()');
        $body = substr($source, $start);

        // Fields confirmed by reading the source file directly.
        // Also includes 'priority' beyond the original spec list.
        foreach ([
            'type',
            'programId',
            'name',
            'commissionRate',
            'commissionType',
            'tierLevel',
            'tierThreshold',
            'lookbackDays',
            'conditions',
            'targetId',
            'priority',
        ] as $field) {
            $this->assertStringContainsString(
                "'{$field}' =>",
                $body,
                "CommissionRuleInterface must declare the '{$field}' GraphQL field",
            );
        }

        // Wave 4 Task 4.4: commission rate fields are redacted from public callers.
        $this->assertStringContainsString(
            'GqlSchemaHelper',
            $source,
            'CommissionRuleInterface must import GqlSchemaHelper for public-schema redaction',
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

    public function testCommissionRuleElementGqlTypeNameMatchesGeneratorName(): void
    {
        // Instantiating CommissionRuleElement directly requires a full Craft/Yii bootstrap.
        // Use a partial mock with the constructor disabled so we can call the real
        // getGqlTypeName() override without triggering Yii's DI container.
        $rule = $this->getMockBuilder(\anvildev\craftkickback\elements\CommissionRuleElement::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $this->assertSame(
            \anvildev\craftkickback\gql\types\generators\CommissionRuleTypeGenerator::getName(),
            $rule->getGqlTypeName(),
            'CommissionRuleElement::getGqlTypeName() must match the name the generator registers, '
            . 'otherwise GraphQL will fail to resolve commission rule instances at query time.',
        );
    }
}
