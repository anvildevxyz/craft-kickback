<?php

declare(strict_types=1);

namespace anvildev\craftkickback\enums;

enum RateType: string
{
    case Percentage = 'percentage';
    case Flat = 'flat';
}
