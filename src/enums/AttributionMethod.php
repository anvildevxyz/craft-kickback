<?php

declare(strict_types=1);

namespace anvildev\craftkickback\enums;

enum AttributionMethod: string
{
    case Cookie = 'cookie';
    case Coupon = 'coupon';
    case DirectLink = 'direct_link';
    case LifetimeCustomer = 'lifetime_customer';
    case Manual = 'manual';
}
