<?php

declare(strict_types=1);

namespace anvildev\craftkickback\gql\types\elements;

use anvildev\craftkickback\gql\interfaces\elements\PayoutInterface;
use craft\gql\types\elements\Element;

class PayoutType extends Element
{
    public function __construct(array $config)
    {
        $config['interfaces'] = [PayoutInterface::getType()];
        parent::__construct($config);
    }
}
