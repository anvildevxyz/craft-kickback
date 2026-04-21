<?php

declare(strict_types=1);

namespace anvildev\craftkickback\events;

use anvildev\craftkickback\elements\AffiliateElement;
use anvildev\craftkickback\elements\CommissionElement;
use anvildev\craftkickback\records\CommissionRecord;
use yii\base\ModelEvent;

class CommissionEvent extends ModelEvent
{
    public ?CommissionRecord $commission = null;
    public ?AffiliateElement $affiliate = null;
    public ?CommissionElement $element = null;
}
