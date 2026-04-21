<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Gql;

use anvildev\craftkickback\gql\arguments\elements\ProgramArguments;
use anvildev\craftkickback\gql\interfaces\elements\ProgramInterface;
use anvildev\craftkickback\gql\queries\KickbackQuery;
use anvildev\craftkickback\gql\types\generators\ProgramTypeGenerator;
use PHPUnit\Framework\TestCase;

class ProgramSchemaTest extends TestCase
{
    public function testProgramInterfaceClassExists(): void
    {
        $this->assertTrue(class_exists(ProgramInterface::class));
    }

    public function testProgramTypeGeneratorImplementsCraftInterface(): void
    {
        $this->assertTrue(
            is_subclass_of(
                ProgramTypeGenerator::class,
                \craft\gql\base\GeneratorInterface::class,
            ),
            'ProgramTypeGenerator must implement Craft\'s GeneratorInterface',
        );
    }

    public function testProgramArgumentsExtendsElementArguments(): void
    {
        $this->assertTrue(
            is_subclass_of(
                ProgramArguments::class,
                \craft\gql\base\ElementArguments::class,
            ),
        );
    }

    public function testKickbackQueryReturnsProgramEntries(): void
    {
        // KickbackQuery::getQueries() calls ProgramInterface::getType() eagerly
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
        // to catch the regression "someone removed the kickbackPrograms
        // query entry from KickbackQuery::getQueries()".
        $queriesSource = file_get_contents(
            __DIR__ . '/../../../src/gql/queries/KickbackQuery.php'
        );
        $this->assertNotFalse($queriesSource, 'KickbackQuery.php must be readable');

        $this->assertStringContainsString(
            "'kickbackPrograms'",
            $queriesSource,
            'KickbackQuery must register the kickbackPrograms list query',
        );
        $this->assertStringContainsString(
            "'kickbackProgram'",
            $queriesSource,
            'KickbackQuery must register the kickbackProgram single query',
        );
        $this->assertStringContainsString(
            'ProgramInterface::getType()',
            $queriesSource,
            'KickbackQuery\'s program entries must reference ProgramInterface::getType()',
        );
    }

    public function testProgramInterfaceFieldsAreExpected(): void
    {
        $source = file_get_contents(
            __DIR__ . '/../../../src/gql/interfaces/elements/ProgramInterface.php'
        );
        $this->assertNotFalse($source, 'ProgramInterface.php must be readable');

        $start = strpos($source, 'function getFieldDefinitions(');
        $this->assertNotFalse($start, 'ProgramInterface must declare getFieldDefinitions()');
        $body = substr($source, $start);

        foreach ([
            'handle',
            'defaultCommissionRate',
            'defaultCommissionType',
            'cookieDuration',
            'allowSelfReferral',
            'programStatus',
        ] as $field) {
            $this->assertStringContainsString(
                "'{$field}' =>",
                $body,
                "ProgramInterface must declare the '{$field}' GraphQL field",
            );
        }

        // Wave 4 Task 4.4: commission-rate fields are redacted from public callers.
        $this->assertStringContainsString(
            'GqlSchemaHelper',
            $source,
            'ProgramInterface must import GqlSchemaHelper for public-schema redaction',
        );

        foreach (['defaultCommissionRate', 'defaultCommissionType'] as $redacted) {
            $fieldPos = strpos($body, "'{$redacted}' =>");
            $this->assertNotFalse($fieldPos, "Field '{$redacted}' must exist in getFieldDefinitions body");
            $this->assertStringContainsString(
                'redactForPublic(',
                substr($body, max(0, $fieldPos - 30), 100),
                "The '{$redacted}' field declaration must be wrapped in redactForPublic()",
            );
        }
    }

    public function testProgramElementGqlTypeNameMatchesGeneratorName(): void
    {
        // Instantiating ProgramElement directly requires a full Craft/Yii bootstrap.
        // Use a partial mock with the constructor disabled so we can call the real
        // getGqlTypeName() override without triggering Yii's DI container.
        $program = $this->getMockBuilder(\anvildev\craftkickback\elements\ProgramElement::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $this->assertSame(
            \anvildev\craftkickback\gql\types\generators\ProgramTypeGenerator::getName(),
            $program->getGqlTypeName(),
            'ProgramElement::getGqlTypeName() must match the name the generator registers, '
            . 'otherwise GraphQL will fail to resolve program instances at query time.',
        );
    }
}
