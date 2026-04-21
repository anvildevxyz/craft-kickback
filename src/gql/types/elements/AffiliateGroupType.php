<?php

declare(strict_types=1);

namespace anvildev\craftkickback\gql\types\elements;

use anvildev\craftkickback\gql\interfaces\elements\AffiliateGroupInterface;
use craft\gql\types\elements\Element;

class AffiliateGroupType extends Element
{
    public function __construct(array $config)
    {
        $config['interfaces'] = [AffiliateGroupInterface::getType()];
        parent::__construct($config);
    }
}
