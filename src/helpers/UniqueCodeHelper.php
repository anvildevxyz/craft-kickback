<?php

declare(strict_types=1);

namespace anvildev\craftkickback\helpers;

use craft\helpers\StringHelper;

class UniqueCodeHelper
{
    /**
     * @param callable(string): bool $existsCheck Returns true if the code already exists.
     */
    public static function generate(
        string $base,
        callable $existsCheck,
        int $maxAttempts = 10,
        int $suffixLength = 4,
    ): string {
        $candidate = $base;
        for ($i = 0; $existsCheck($candidate) && $i < $maxAttempts; $i++) {
            $candidate = $base . '-' . StringHelper::randomString($suffixLength);
        }
        return $candidate;
    }
}
