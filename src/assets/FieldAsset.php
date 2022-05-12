<?php
/**
 * TableMate plugin for Craft CMS 4.x
 *
 * The tables have turned, mate!
 *
 * @link      https://www.vaersaagod.no
 * @copyright Copyright (c) 2022 Værsågod
 */

namespace vaersaagod\tablemate\assets;

use craft\web\assets\tablesettings\TableSettingsAsset;

class FieldAsset extends \craft\web\AssetBundle
{

    /**
     * @inheritdoc
     */
    public function init()
    {
        // define the path that your publishable resources live
        $this->sourcePath = '@vaersaagod/tablemate/assets/dist';

        // define the dependencies
        $this->depends = [
            TableSettingsAsset::class,
        ];

        // define the relative path to CSS/JS files that should be registered with the page
        // when this asset bundle is registered
        $this->js = [
            'tablemate.js',
        ];

        $this->css = [
            'tablemate.css',
        ];

        parent::init();
    }

}
