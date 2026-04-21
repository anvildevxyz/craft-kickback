<?php

declare(strict_types=1);

namespace anvildev\craftkickback\enums;

enum AffiliateStatus: string
{
    case Active = 'active';
    case Pending = 'pending';
    case Suspended = 'suspended';
    case Rejected = 'rejected';
}
