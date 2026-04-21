<?php

declare(strict_types=1);

namespace anvildev\craftkickback\gql\types\elements;

use anvildev\craftkickback\gql\interfaces\elements\CommissionRuleInterface;
use craft\gql\types\elements\Element;

class CommissionRuleType extends Element
{
    public function __construct(array $config)
    {
        $config['interfaces'] = [CommissionRuleInterface::getType()];
        parent::__construct($config);
    }
}
