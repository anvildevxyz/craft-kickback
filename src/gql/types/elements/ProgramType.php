<?php

declare(strict_types=1);

namespace anvildev\craftkickback\gql\types\elements;

use anvildev\craftkickback\gql\interfaces\elements\ProgramInterface;
use craft\gql\types\elements\Element;

class ProgramType extends Element
{
    public function __construct(array $config)
    {
        $config['interfaces'] = [ProgramInterface::getType()];
        parent::__construct($config);
    }
}
