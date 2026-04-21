<?php

declare(strict_types=1);

namespace anvildev\craftkickback\gql\types\elements;

use anvildev\craftkickback\gql\interfaces\elements\ReferralInterface;
use craft\gql\types\elements\Element;

class ReferralType extends Element
{
    public function __construct(array $config)
    {
        $config['interfaces'] = [ReferralInterface::getType()];
        parent::__construct($config);
    }
}
