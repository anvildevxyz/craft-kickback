<?php

declare(strict_types=1);

namespace anvildev\craftkickback\gql\types\elements;

use anvildev\craftkickback\gql\interfaces\elements\AffiliateInterface;
use craft\gql\types\elements\Element;

class AffiliateType extends Element
{
    public function __construct(array $config)
    {
        $config['interfaces'] = [AffiliateInterface::getType()];
        parent::__construct($config);
    }
}
