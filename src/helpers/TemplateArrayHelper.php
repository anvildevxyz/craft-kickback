<?php

declare(strict_types=1);

namespace anvildev\craftkickback\helpers;

use craft\db\ActiveRecord;

/**
 * Converts legacy Yii ActiveRecords into plain arrays for Twig.
 *
 * Craft 6's Twig bridge only fast-paths its own base classes. A plugin
 * ActiveRecord falls through to Twig's native resolver, which checks
 * isset() first — Yii's __isset() returns false for null-valued columns —
 * then tries a bare method call that ActiveRecord::__call() turns into a
 * 500. Plain arrays never take that path.
 *
 * @author anvildev <dev@anvil.xyz>
 * @since 1.3.0
 */
class TemplateArrayHelper
{
    /**
     * @return array<string, mixed>
     */
    public static function fromRecord(ActiveRecord $record): array
    {
        return $record->toArray();
    }

    /**
     * @param ActiveRecord[] $records
     * @return list<array<string, mixed>>
     */
    public static function fromRecords(array $records): array
    {
        return array_map(self::fromRecord(...), $records);
    }
}
