<?php

declare(strict_types=1);

namespace anvildev\craftkickback\enums;

enum CommissionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Paid = 'paid';
    case Reversed = 'reversed';
    case Rejected = 'rejected';
}
