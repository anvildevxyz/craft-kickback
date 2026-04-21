<?php

declare(strict_types=1);

namespace anvildev\craftkickback\enums;

enum CommissionRuleType: string
{
    case Product = 'product';
    case Category = 'category';
    case Tiered = 'tiered';
    case Bonus = 'bonus';
    case MlmTier = 'mlm_tier';
}
