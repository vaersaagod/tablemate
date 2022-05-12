<?php

namespace vaersaagod\tablemate;

use Craft;
use craft\base\ElementInterface;
use craft\fields\data\ColorData;
use craft\fields\Table;
use craft\helpers\Cp;
use craft\helpers\DateTimeHelper;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\helpers\Template;
use craft\validators\ColorValidator;
use craft\validators\HandleValidator;
use craft\validators\UrlValidator;
use craft\web\assets\tablesettings\TableSettingsAsset;
use craft\web\assets\timepicker\TimepickerAsset;

use LitEmoji\LitEmoji;

use vaersaagod\tablemate\assets\FieldAsset;

use yii\db\Schema;
use yii\validators\EmailValidator;

class Field extends \craft\base\Field
{

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('tablemate', 'TableMate field');
    }

    /**
     * @inheritdoc
     */
    public static function valueType(): string
    {
        return 'array|null';
    }

    /**
     * @var string|null
     */
    public ?string $columnsLabel = null;

    /**
     * @var string|null
     */
    public ?string $columnsInstructions = null;

    /**
     * @var string|null
     */
    public ?string $columnsAddRowLabel = null;

    /**
     * @var string|null
     */
    public ?string $rowsLabel = null;

    /**
     * @var string|null
     */
    public ?string $rowsInstructions = null;

    /**
     * @var string|null
     */
    public ?string $rowsAddRowLabel = null;

    /**
     * @var string[]|string
     */
    public array|string $allowedTypeOptions = '*';

    /**
     * @var string The type of database column the field should have in the content table
     * @phpstan-var 'auto'|Schema::TYPE_STRING|Schema::TYPE_TEXT|'mediumtext'
     */
    public string $columnType = Schema::TYPE_TEXT;

    /**
     * @inheritdoc
     */
    public function getContentColumnType(): string
    {
        return $this->columnType;
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('tablemate/settings', ['field' => $this]);
    }

    /**
     * @inheritdoc
     */
    public function useFieldset(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    protected function inputHtml(mixed $value, ?ElementInterface $element = null): string
    {
        return $this->_getInputHtml($value, $element, false);
    }

    /**
     * @inheritdoc
     */
    public function getElementValidationRules(): array
    {
        return ['validateTableData'];
    }

    /**
     * Validates the table data.
     *
     * @param ElementInterface $element
     */
    public function validateTableData(ElementInterface $element): void
    {
        $value = $element->getFieldValue($this->handle);

        if (!empty($value['columns'] ?? null) && !empty($value['rows'] ?? null)) {
            foreach ($value['rows'] as &$row) {
                foreach ($value['columns'] as $colId => $col) {
                    if (is_string($row[$colId])) {
                        // Trim the value before validating
                        $row[$colId] = trim($row[$colId]);
                    }
                    if (!$this->_validateCellValue($col['type'], $row[$colId], $error)) {
                        $element->addError($this->handle, $error);
                    }
                }
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function normalizeValue(mixed $value, ?ElementInterface $element = null): mixed
    {

        if (is_string($value) && !empty($value)) {
            $value = Json::decodeIfJson($value);
        } else if ($value === null && $this->isFresh($element)) {
            $value = [
                'columns' => [],
                'rows' => [],
                'table' => null,
            ];
        }

        if (!is_array($value) || empty($value['columns'] ?? null)) {
            return null;
        }

        // TODO convert numeric keys to col1 etc
        if (!is_array($value['rows'])) {
            $value['rows'] = [];
        }

        foreach ($value['rows'] as &$row) {
            foreach ($value['columns'] as $colId => &$col) {
                if (array_key_exists($colId, $row)) {
                    $cellValue = $row[$colId];
                } else {
                    $cellValue = null;
                }
                if (empty($col['type'])) {
                    $col['type'] = 'singleline';
                }
                $cellValue = $this->_normalizeCellValue($col['type'], $cellValue);
                $row[$colId] = $cellValue;
            }
        }

        return $value;
    }

    /**
     * @inheritdoc
     */
    public function serializeValue(mixed $value, ?ElementInterface $element = null): mixed
    {

        if (!is_array($value) || empty($value['columns'] ?? null)) {
            return null;
        }

        if (!is_array($value['rows'] ?? null)) {
            $value['rows'] = [];
        }

        $columns = $value['columns'];

        foreach ($value['rows'] as $rowId => &$row) {
            foreach (array_keys($columns) as $colId) {
                if (is_string($row[$colId]) && in_array($columns[$colId]['type'], ['singleline', 'multiline'], true)) {
                    $row[$colId] = LitEmoji::unicodeToShortcode($row[$colId]);
                }
            }
        }

        // Drop keys from the columns
        $value['columns'] = array_values($value['columns']);

        // Drop keys from the rows
        $value['rows'] = array_values($value['rows']);
        foreach ($value['rows'] as &$row) {
            if (is_array($row)) {
                $row = array_values($row);
            }
        }

        return \craft\base\Field::serializeValue($value, $element);

    }

    /**
     * @inheritdoc
     */
    protected function searchKeywords(mixed $value, ElementInterface $element): string
    {
        if (!is_array($value) || empty($value['columns'] ?? null) || empty($value['rows'] ?? '')) {
            return '';
        }

        $keywords = [];

        foreach ($value['rows'] as $row) {
            foreach (array_keys($value['columns']) as $colId) {
                if (isset($row[$colId]) && !$row[$colId] instanceof DateTime) {
                    $keywords[] = $row[$colId];
                }
            }
        }

        return implode(' ', $keywords);
    }

    /**
     * @inheritdoc
     */
    public function getStaticHtml(mixed $value, ElementInterface $element): string
    {
        return $this->_getInputHtml($value, $element, true);
    }

    /**
     * @return array
     */
    public function getTypeOptions(): array
    {
        return [
            'checkbox' => Craft::t('app', 'Checkbox'),
            'color' => Craft::t('app', 'Color'),
            'date' => Craft::t('app', 'Date'),
            'select' => Craft::t('app', 'Dropdown'),
            'email' => Craft::t('app', 'Email'),
            'lightswitch' => Craft::t('app', 'Lightswitch'),
            'multiline' => Craft::t('app', 'Multi-line text'),
            'number' => Craft::t('app', 'Number'),
            'singleline' => Craft::t('app', 'Single-line text'),
            'time' => Craft::t('app', 'Time'),
            'url' => Craft::t('app', 'URL'),
        ];
    }

    /**
     * @return array
     */
    public function getAllowedTypeOptions(): array
    {
        $allTypeOptions = $this->getTypeOptions();
        if (!is_array($this->allowedTypeOptions)) {
            return $allTypeOptions;
        }

        return array_reduce(array_keys($allTypeOptions), function (array $carry, string $type) use ($allTypeOptions) {
            if (!in_array($type, $this->allowedTypeOptions)) {
                return $carry;
            }
            $carry[$type] = $allTypeOptions[$type];
            return $carry;
        }, []);
    }

    /**
     * Normalizes a cell’s value.
     *
     * @param string $type The cell type
     * @param mixed $value The cell value
     * @return mixed
     * @see normalizeValue()
     */
    private function _normalizeCellValue(string $type, mixed $value): mixed
    {
        switch ($type) {
            case 'color':
                if ($value instanceof ColorData) {
                    return $value;
                }

                if (!$value || $value === '#') {
                    return null;
                }

                $value = strtolower($value);

                if ($value[0] !== '#') {
                    $value = '#' . $value;
                }

                if (strlen($value) === 4) {
                    $value = '#' . $value[1] . $value[1] . $value[2] . $value[2] . $value[3] . $value[3];
                }

                return new ColorData($value);

            case 'multiline':
            case 'singleline':
                if ($value !== null) {
                    $value = LitEmoji::shortcodeToUnicode($value);
                    return trim(preg_replace('/\R/u', "\n", $value));
                }
            // no break
            case 'date':
            case 'time':
                return DateTimeHelper::toDateTime($value) ?: null;
        }

        return $value;
    }

    /**
     * Validates a cell’s value.
     *
     * @param string $type The cell type
     * @param mixed $value The cell value
     * @param string|null $error The error text to set on the element
     * @return bool Whether the value is valid
     * @see normalizeValue()
     */
    private function _validateCellValue(string $type, mixed $value, ?string &$error = null): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        switch ($type) {
            case 'color':
                /** @var ColorData $value */
                $value = $value->getHex();
                $validator = new ColorValidator();
                break;
            case 'url':
                $validator = new UrlValidator();
                break;
            case 'email':
                $validator = new EmailValidator();
                break;
            default:
                return true;
        }

        $validator->message = str_replace('{attribute}', '{value}', $validator->message);
        return $validator->validate($value, $error);
    }

    /**
     * @param $value
     * @param ElementInterface|null $element
     * @param bool $static
     * @return string
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \yii\base\Exception
     * @throws \yii\base\InvalidConfigException
     */
    private function _getInputHtml($value, ElementInterface $element = null, bool $static = false): string
    {

        if (!is_array($value)) {
            $value = [];
        }

        $name = $this->handle;

        $columnsInputName = "{$name}[columns]";
        $rowsInputName = "{$name}[rows]";
        $columnsInputId = Html::id("$name-columns");
        $rowsInputId = Html::id("$name-rows");

        $columns = $value['columns'] ?? [['type' => 'singleline']];
        $rows = $value['rows'] ?? [];

        // Explicitly set each cell value to an array with a 'value' key
        $checkForErrors = $element && $element->hasErrors($this->handle);
        foreach ($rows as &$row) {
            foreach ($columns as $colId => $col) {
                if (isset($row[$colId])) {
                    $hasErrors = $checkForErrors && !$this->_validateCellValue($col['type'], $row[$colId]);
                    $row[$colId] = [
                        'value' => $row[$colId],
                        'hasErrors' => $hasErrors,
                    ];
                }
            }
        }
        unset($row);

        // Prep col settings
        $columnSettings = [
            'heading' => [
                'heading' => Craft::t('app', 'Heading'),
                'class' => '',
                'type' => 'singleline',
            ],
        ];

        // Get type options
        $typeOptions = $this->getAllowedTypeOptions();

        // Make sure they are sorted alphabetically (post-translation)
        asort($typeOptions);

        // Include the type options if there are more than one
        if (count($typeOptions) > 1) {
            $columnSettings['type'] = [
                'heading' => Craft::t('app', 'Type'),
                'class' => 'thin',
                'type' => 'select',
                'options' => $typeOptions,
            ];
        }

        $dropdownSettingsCols = [
            'label' => [
                'heading' => Craft::t('app', 'Option Label'),
                'type' => 'singleline',
                'autopopulate' => 'value',
                'class' => 'option-label',
            ],
            'value' => [
                'heading' => Craft::t('app', 'Value'),
                'type' => 'singleline',
                'class' => 'option-value code',
            ],
            'default' => [
                'heading' => Craft::t('app', 'Default?'),
                'type' => 'checkbox',
                'radioMode' => true,
                'class' => 'option-default thin',
            ],
        ];

        $dropdownSettingsHtml = Cp::editableTableFieldHtml([
            'label' => Craft::t('app', 'Dropdown Options'),
            'instructions' => Craft::t('app', 'Define the available options.'),
            'id' => '__ID__',
            'name' => '__NAME__',
            'addRowLabel' => Craft::t('app', 'Add an option'),
            'allowAdd' => true,
            'allowReorder' => true,
            'allowDelete' => true,
            'cols' => $dropdownSettingsCols,
            'initJs' => false,
            'static' => $static,
        ]);

        $view = Craft::$app->getView();

        $view->registerAssetBundle(TimepickerAsset::class);
        $view->registerAssetBundle(TableSettingsAsset::class);
        $view->registerJs('new Craft.TableFieldSettings(' .
            Json::encode($view->namespaceInputName($columnsInputName), JSON_UNESCAPED_UNICODE) . ', ' .
            Json::encode($view->namespaceInputName($rowsInputName), JSON_UNESCAPED_UNICODE) . ', ' .
            Json::encode($columns, JSON_UNESCAPED_UNICODE) . ', ' .
            Json::encode($rows, JSON_UNESCAPED_UNICODE) . ', ' .
            Json::encode($columnSettings, JSON_UNESCAPED_UNICODE) . ', ' .
            Json::encode($dropdownSettingsHtml, JSON_UNESCAPED_UNICODE) . ', ' .
            Json::encode($dropdownSettingsCols, JSON_UNESCAPED_UNICODE) .
            ');');

        $view->registerAssetBundle(FieldAsset::class);

        $columnsField = Cp::editableTableFieldHtml([
            'label' => Craft::t('site', $this->columnsLabel),
            'instructions' => Craft::t('site', $this->columnsInstructions),
            'id' => $columnsInputId,
            'name' => $columnsInputName,
            'cols' => $columnSettings,
            'rows' => $columns,
            'addRowLabel' => $this->columnsAddRowLabel ?? Craft::t('tablemate', 'Add column'),
            'initJs' => false,
            'allowAdd' => true,
            'allowDelete' => true,
            'allowReorder' => true,
            'static' => $static,
        ]);

        $rowsField = Cp::editableTableFieldHtml([
            'label' => Craft::t('site', $this->rowsLabel),
            'instructions' => Craft::t('site', $this->rowsInstructions),
            'id' => $rowsInputId,
            'name' => $rowsInputName,
            'cols' => $columns,
            'rows' => $rows,
            'addRowLabel' => $this->rowsAddRowLabel ?? Craft::t('tablemate', 'Add row'),
            'initJs' => false,
            'allowAdd' => true,
            'allowDelete' => true,
            'allowReorder' => true,
            'static' => $static,
        ]);

        return Craft::$app->getView()->renderTemplate('tablemate/input.twig', [
            'field' => $this,
            'columns' => $columns,
            'columnsField' => $columnsField,
            'rowsField' => $rowsField,
        ]);
    }

}
