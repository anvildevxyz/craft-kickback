<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Gql;

use anvildev\craftkickback\gql\arguments\elements\PayoutArguments;
use anvildev\craftkickback\gql\interfaces\elements\PayoutInterface;
use anvildev\craftkickback\gql\queries\KickbackQuery;
use anvildev\craftkickback\gql\types\generators\PayoutTypeGenerator;
use PHPUnit\Framework\TestCase;

class PayoutSchemaTest extends TestCase
{
    public function testPayoutInterfaceClassExists(): void
    {
        $this->assertTrue(class_exists(PayoutInterface::class));
    }

    public function testPayoutTypeGeneratorImplementsCraftInterface(): void
    {
        $this->assertTrue(
            is_subclass_of(
                PayoutTypeGenerator::class,
                \craft\gql\base\GeneratorInterface::class,
            ),
            'PayoutTypeGenerator must implement Craft\'s GeneratorInterface',
        );
    }

    public function testPayoutArgumentsExtendsElementArguments(): void
    {
        $this->assertTrue(
            is_subclass_of(
                PayoutArguments::class,
                \craft\gql\base\ElementArguments::class,
            ),
        );
    }

    public function testKickbackQueryReturnsPayoutEntries(): void
    {
        // KickbackQuery::getQueries() calls PayoutInterface::getType() eagerly
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
        // to catch the regression "someone removed the kickbackPayouts
        // query entry from KickbackQuery::getQueries()".
        $queriesSource = file_get_contents(
            __DIR__ . '/../../../src/gql/queries/KickbackQuery.php'
        );
        $this->assertNotFalse($queriesSource, 'KickbackQuery.php must be readable');

        $this->assertStringContainsString(
            "'kickbackPayouts'",
            $queriesSource,
            'KickbackQuery must register the kickbackPayouts list query',
        );
        $this->assertStringContainsString(
            "'kickbackPayout'",
            $queriesSource,
            'KickbackQuery must register the kickbackPayout single query',
        );
        $this->assertStringContainsString(
            'PayoutInterface::getType()',
            $queriesSource,
            'KickbackQuery\'s payout entries must reference PayoutInterface::getType()',
        );
    }

    public function testPayoutInterfaceFieldsAreExpected(): void
    {
        $source = file_get_contents(
            __DIR__ . '/../../../src/gql/interfaces/elements/PayoutInterface.php'
        );
        $this->assertNotFalse($source, 'PayoutInterface.php must be readable');

        $start = strpos($source, 'function getFieldDefinitions(');
        $this->assertNotFalse($start, 'PayoutInterface must declare getFieldDefinitions()');
        $body = substr($source, $start);

        foreach ([
            'affiliateId',
            'amount',
            'currency',
            'method',
            'payoutStatus',
            'transactionId',
            'gatewayBatchId',
            'notes',
            'createdByUserId',
        ] as $field) {
            $this->assertStringContainsString(
                "'{$field}' =>",
                $body,
                "PayoutInterface must declare the '{$field}' GraphQL field",
            );
        }

        // Wave 4 Task 4.4: financial and operational fields are redacted from public callers.
        $this->assertStringContainsString(
            'GqlSchemaHelper',
            $source,
            'PayoutInterface must import GqlSchemaHelper for public-schema redaction',
        );

        foreach (['amount', 'method', 'payoutStatus', 'transactionId', 'gatewayBatchId', 'notes'] as $redacted) {
            $fieldPos = strpos($body, "'{$redacted}' =>");
            $this->assertNotFalse($fieldPos, "Field '{$redacted}' must exist in getFieldDefinitions body");
            $this->assertStringContainsString(
                'redactForPublic(',
                substr($body, max(0, $fieldPos - 30), 100),
                "The '{$redacted}' field declaration must be wrapped in redactForPublic()",
            );
        }
    }

    public function testPayoutElementGqlTypeNameMatchesGeneratorName(): void
    {
        // Instantiating PayoutElement directly requires a full Craft/Yii bootstrap.
        // Use a partial mock with the constructor disabled so we can call the real
        // getGqlTypeName() override without triggering Yii's DI container.
        $payout = $this->getMockBuilder(\anvildev\craftkickback\elements\PayoutElement::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $this->assertSame(
            \anvildev\craftkickback\gql\types\generators\PayoutTypeGenerator::getName(),
            $payout->getGqlTypeName(),
            'PayoutElement::getGqlTypeName() must match the name the generator registers, '
            . 'otherwise GraphQL will fail to resolve payout instances at query time.',
        );
    }
}
