<?php

declare(strict_types=1);

namespace anvildev\craftkickback\events;

use anvildev\craftkickback\elements\AffiliateElement;
use anvildev\craftkickback\elements\PayoutElement;
use yii\base\ModelEvent;

class PayoutEvent extends ModelEvent
{
    public ?PayoutElement $payout = null;
    public ?AffiliateElement $affiliate = null;
}
