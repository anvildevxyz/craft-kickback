<?php

declare(strict_types=1);

namespace anvildev\craftkickback\helpers;

/**
 * Utility helpers for deterministic simulation workloads.
 */
class SimulationHelper
{
    /**
     * Parse weighted scenario mix strings:
     * "product:25,category:20,tiered:20,bonus:15,group:10,program:10"
     *
     * @return array<string, int> [scenario => weight]
     */
    public static function parseWeightedMix(string $input): array
    {
        $weights = [];
        foreach (explode(',', $input) as $part) {
            if (!str_contains($part = trim($part), ':')) {
                continue;
            }
            [$key, $raw] = array_map('trim', explode(':', $part, 2));
            if ($key !== '' && ($w = (int)$raw) > 0) {
                $weights[$key] = $w;
            }
        }
        return $weights;
    }

    /**
     * Pick one key based on integer weighted distribution.
     *
     * @param array<string, int> $weights
     */
    public static function pickWeighted(array $weights): ?string
    {
        if (!$weights || ($total = array_sum($weights)) <= 0) {
            return null;
        }
        $roll = random_int(1, $total);
        $running = 0;
        foreach ($weights as $key => $weight) {
            if ($roll <= ($running += $weight)) {
                return $key;
            }
        }
        return array_key_first($weights);
    }
}
