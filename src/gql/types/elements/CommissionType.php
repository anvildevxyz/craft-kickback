<?php

declare(strict_types=1);

namespace anvildev\craftkickback\gql\types\elements;

use anvildev\craftkickback\gql\interfaces\elements\CommissionInterface;
use craft\gql\types\elements\Element;

class CommissionType extends Element
{
    public function __construct(array $config)
    {
        $config['interfaces'] = [CommissionInterface::getType()];
        parent::__construct($config);
    }
}
