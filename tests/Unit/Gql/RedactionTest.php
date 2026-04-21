<?php

declare(strict_types=1);

namespace anvildev\craftkickback\tests\Unit\Gql;

use anvildev\craftkickback\gql\GqlSchemaHelper;
use PHPUnit\Framework\TestCase;

class RedactionTest extends TestCase
{
    public function testHelperClassExists(): void
    {
        $this->assertTrue(class_exists(GqlSchemaHelper::class));
    }

    public function testRedactForPublicWrapsFieldWithResolveClosure(): void
    {
        $original = [
            'name' => 'pendingBalance',
            'type' => \GraphQL\Type\Definition\Type::float(),
        ];

        $wrapped = GqlSchemaHelper::redactForPublic($original);

        $this->assertArrayHasKey('resolve', $wrapped);
        $this->assertIsCallable($wrapped['resolve']);
        $this->assertSame('pendingBalance', $wrapped['name']);
    }

    public function testIsPublicSchemaDefaultsToTrueOnFailure(): void
    {
        // In a unit-test context without a Craft bootstrap, the
        // helper's try/catch catches the framework-not-loaded
        // failure and returns true - which is the safe default
        // (redact, don't leak).
        $this->assertTrue(GqlSchemaHelper::isPublicSchema());
    }

    public function testRedactedFieldReturnsNullForPublicSchema(): void
    {
        $wrapped = GqlSchemaHelper::redactForPublic([
            'name' => 'pendingBalance',
            'type' => \GraphQL\Type\Definition\Type::float(),
        ]);

        $source = (object)['pendingBalance' => 42.00];
        $resolveInfo = (object)['fieldName' => 'pendingBalance'];

        // In a unit-test context isPublicSchema() returns true, so
        // the resolver must return null regardless of source value.
        $result = ($wrapped['resolve'])($source, [], null, $resolveInfo);
        $this->assertNull($result);
    }
}
