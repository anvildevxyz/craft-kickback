<?php

declare(strict_types=1);

namespace anvildev\craftkickback\events;

use anvildev\craftkickback\elements\AffiliateElement;
use yii\base\ModelEvent;

class AffiliateEvent extends ModelEvent
{
    public AffiliateElement $affiliate;
    public bool $isNew = false;
}
