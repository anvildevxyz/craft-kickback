<?php

declare(strict_types=1);

namespace anvildev\craftkickback\helpers;

use craft\helpers\DateTimeHelper;

class DateHelper
{
    /** UTC now as 'Y-m-d H:i:s'. */
    public static function nowString(): string
    {
        return DateTimeHelper::currentUTCDateTime()->format('Y-m-d H:i:s');
    }

    /**
     * UTC cutoff as 'Y-m-d H:i:s'. $interval is any PHP relative time
     * expression, e.g. '-24 hours' or "-{$days} days".
     */
    public static function pastCutoffString(string $interval): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify($interval)->format('Y-m-d H:i:s');
    }
}
