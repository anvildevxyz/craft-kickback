<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Gql;

use anvildev\craftkickback\gql\arguments\elements\CommissionArguments;
use anvildev\craftkickback\gql\interfaces\elements\CommissionInterface;
use anvildev\craftkickback\gql\queries\KickbackQuery;
use anvildev\craftkickback\gql\types\generators\CommissionTypeGenerator;
use PHPUnit\Framework\TestCase;

class CommissionSchemaTest extends TestCase
{
    public function testCommissionInterfaceClassExists(): void
    {
        $this->assertTrue(class_exists(CommissionInterface::class));
    }

    public function testCommissionTypeGeneratorImplementsCraftInterface(): void
    {
        $this->assertTrue(
            is_subclass_of(
                CommissionTypeGenerator::class,
                \craft\gql\base\GeneratorInterface::class,
            ),
            'CommissionTypeGenerator must implement Craft\'s GeneratorInterface',
        );
    }

    public function testCommissionArgumentsExtendsElementArguments(): void
    {
        $this->assertTrue(
            is_subclass_of(
                CommissionArguments::class,
                \craft\gql\base\ElementArguments::class,
            ),
        );
    }

    public function testKickbackQueryReturnsCommissionEntries(): void
    {
        // KickbackQuery::getQueries() calls CommissionInterface::getType() eagerly
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
        // to catch the regression "someone removed the kickbackCommissions
        // query entry from KickbackQuery::getQueries()".
        $queriesSource = file_get_contents(
            __DIR__ . '/../../../src/gql/queries/KickbackQuery.php'
        );
        $this->assertNotFalse($queriesSource, 'KickbackQuery.php must be readable');

        $this->assertStringContainsString(
            "'kickbackCommissions'",
            $queriesSource,
            'KickbackQuery must register the kickbackCommissions list query',
        );
        $this->assertStringContainsString(
            "'kickbackCommission'",
            $queriesSource,
            'KickbackQuery must register the kickbackCommission single query',
        );
        $this->assertStringContainsString(
            'CommissionInterface::getType()',
            $queriesSource,
            'KickbackQuery\'s commission entries must reference CommissionInterface::getType()',
        );
    }

    public function testCommissionInterfaceFieldsAreExpected(): void
    {
        $source = file_get_contents(
            __DIR__ . '/../../../src/gql/interfaces/elements/CommissionInterface.php'
        );
        $this->assertNotFalse($source, 'CommissionInterface.php must be readable');

        $start = strpos($source, 'function getFieldDefinitions(');
        $this->assertNotFalse($start, 'CommissionInterface must declare getFieldDefinitions()');
        $body = substr($source, $start);

        foreach ([
            'affiliateId',
            'referralId',
            'amount',
            'originalAmount',
            'currency',
            'rate',
            'rateType',
            'tier',
            'commissionStatus',
            'payoutId',
            'ruleApplied',
        ] as $field) {
            $this->assertStringContainsString(
                "'{$field}' =>",
                $body,
                "CommissionInterface must declare the '{$field}' GraphQL field",
            );
        }

        // Wave 4 Task 4.4: financial and rate fields are redacted from public callers.
        $this->assertStringContainsString(
            'GqlSchemaHelper',
            $source,
            'CommissionInterface must import GqlSchemaHelper for public-schema redaction',
        );

        foreach (['amount', 'originalAmount', 'rate', 'rateType', 'commissionStatus'] as $redacted) {
            $fieldPos = strpos($body, "'{$redacted}' =>");
            $this->assertNotFalse($fieldPos, "Field '{$redacted}' must exist in getFieldDefinitions body");
            $this->assertStringContainsString(
                'redactForPublic(',
                substr($body, max(0, $fieldPos - 30), 100),
                "The '{$redacted}' field declaration must be wrapped in redactForPublic()",
            );
        }
    }

    public function testCommissionElementGqlTypeNameMatchesGeneratorName(): void
    {
        // Instantiating CommissionElement directly requires a full Craft/Yii bootstrap.
        // Use a partial mock with the constructor disabled so we can call the real
        // getGqlTypeName() override without triggering Yii's DI container.
        $commission = $this->getMockBuilder(\anvildev\craftkickback\elements\CommissionElement::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $this->assertSame(
            \anvildev\craftkickback\gql\types\generators\CommissionTypeGenerator::getName(),
            $commission->getGqlTypeName(),
            'CommissionElement::getGqlTypeName() must match the name the generator registers, '
            . 'otherwise GraphQL will fail to resolve commission instances at query time.',
        );
    }
}
