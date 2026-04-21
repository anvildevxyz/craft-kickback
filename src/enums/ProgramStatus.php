<?php

declare(strict_types=1);

namespace anvildev\craftkickback\enums;

enum ProgramStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Archived = 'archived';
}
