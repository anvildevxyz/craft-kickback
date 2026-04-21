<?php

declare(strict_types=1);

namespace anvildev\craftkickback\events;

use anvildev\craftkickback\elements\AffiliateElement;
use anvildev\craftkickback\records\ReferralRecord;
use yii\base\ModelEvent;

class ReferralEvent extends ModelEvent
{
    public ?AffiliateElement $affiliate = null;
    public ?ReferralRecord $referral = null;
    public bool $isNew = false;
}
