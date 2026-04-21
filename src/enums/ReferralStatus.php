<?php

declare(strict_types=1);

namespace anvildev\craftkickback\enums;

enum ReferralStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Paid = 'paid';
    case Flagged = 'flagged';
}
