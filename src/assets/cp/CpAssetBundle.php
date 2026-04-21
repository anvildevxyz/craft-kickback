<?php

declare(strict_types=1);

namespace anvildev\craftkickback\assets\cp;

use craft\web\AssetBundle;

class CpAssetBundle extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = __DIR__ . '/dist';

        $this->css = [
            'css/kickback-cp.css',
        ];

        parent::init();
    }
}
