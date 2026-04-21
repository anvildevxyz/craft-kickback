<?php

declare(strict_types=1);

namespace anvildev\craftkickback\enums;

enum PayoutStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Rejected = 'rejected';
    case Reversed = 'reversed';
}
