<?php

declare(strict_types=1);

namespace anvildev\craftkickback\enums;

enum PayoutMethod: string
{
    case PayPal = 'paypal';
    case Stripe = 'stripe';
    case Manual = 'manual';
}
