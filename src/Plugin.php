<?php
/**
 * TableMate plugin for Craft CMS 4.x
 *
 * The tables have turned, mate!
 *
 * @link      https://www.vaersaagod.no
 * @copyright Copyright (c) 2022 Værsågod
 */

namespace vaersaagod\tablemate;

use Craft;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Fields;

use yii\base\Event;

class Plugin extends craft\base\Plugin
{

    /**
     * @inheritdoc
     */
    public string $schemaVersion = '1.0.0';

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        // Register field type
        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            static function (RegisterComponentTypesEvent $event) {
                $event->types[] = Field::class;
            }
        );
    }
}
