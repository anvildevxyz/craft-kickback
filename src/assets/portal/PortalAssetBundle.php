<?php

declare(strict_types=1);

namespace anvildev\craftkickback\assets\portal;

use craft\web\AssetBundle;

class PortalAssetBundle extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = __DIR__ . '/dist';

        $this->css = [
            'css/portal.css',
        ];

        parent::init();
    }
}
