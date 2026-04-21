<?php

declare(strict_types=1);

namespace anvildev\craftkickback\enums;

enum AttributionModel: string
{
    case FirstClick = 'first_click';
    case LastClick = 'last_click';
    case Linear = 'linear';
}
