<?php

declare(strict_types=1);

namespace anvildev\craftkickback\gql;

use Craft;

/**
 * Public-vs-admin GraphQL schema detection + field redaction helpers.
 * Sensitive fields (balances, emails, amounts, transaction ids) must be
 * redacted for public callers.
 */
final class GqlSchemaHelper
{
    /**
     * True when the active schema is the public (unauthenticated) one.
     * Defaults to true on error - fail safe: if we can't tell, redact.
     */
    public static function isPublicSchema(): bool
    {
        try {
            $schema = Craft::$app->getGql()->getActiveSchema();
            return (bool)($schema->isPublic ?? true);
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * Wrap a field definition so it resolves to null for public-schema
     * callers. The field stays visible in introspection; existing clients
     * just get no data.
     *
     * @param array<string, mixed> $fieldDefinition
     * @return array<string, mixed>
     */
    public static function redactForPublic(array $fieldDefinition): array
    {
        $fieldDefinition['resolve'] = static function($source, array $arguments, $context, $resolveInfo) use ($fieldDefinition) {
            if (self::isPublicSchema() || $source === null) {
                return null;
            }

            $field = $resolveInfo->fieldName ?? $fieldDefinition['name'] ?? null;
            if ($field === null) {
                return null;
            }

            return is_array($source) ? ($source[$field] ?? null) : ($source->$field ?? null);
        };

        return $fieldDefinition;
    }
}
