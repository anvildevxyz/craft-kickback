<?php

declare(strict_types=1);

namespace anvildev\craftkickback\events;

use anvildev\craftkickback\records\ReferralRecord;
use yii\base\ModelEvent;

class FraudEvent extends ModelEvent
{
    public ?ReferralRecord $referral = null;

    /** @var string[] */
    public array $fraudFlags = [];
}
