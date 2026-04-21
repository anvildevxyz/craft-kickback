<?php

declare(strict_types=1);

namespace anvildev\craftkickback\events;

use anvildev\craftkickback\elements\ProgramElement;
use yii\base\ModelEvent;

class ProgramEvent extends ModelEvent
{
    public ?ProgramElement $program = null;
    public bool $isNew = false;
}
