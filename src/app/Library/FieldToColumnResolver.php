<?php

namespace Backpack\Settings\app\Library;

/**
 * Translates a Backpack field definition into a column definition for the list view.
 *
 * Strategy: strip form-only keys, pass everything else through, then remap the type.
 * Blacklist (not whitelist) because column attributes vary per type and per Backpack release.
 */
class FieldToColumnResolver
{
    // Form-only keys that must NOT leak onto a column definition.
    // `view_namespace` is especially dangerous: it points at field views.
    // `withFiles` / `withMedia` are intentionally NOT here — they're macros registered on
    // CrudColumn too (see backpack/crud/src/macros.php) and drive uploader hydration.
    protected const STRIP_KEYS = [
        'attributes', 'wrapperAttributes', 'placeholder', 'hint',
        'readonly', 'disabled', 'autocomplete',
        'validation', 'validationRules', 'validationMessages',
        'allows_null', 'allows_multiple',
        'tab', 'fake', 'store_in', 'dependencies', 'on_change', 'new_item_label',
        'view_namespace',
        'field_unique_name', 'parent_field', 'parentFieldName',
        'container_name', 'show_asterisk', 'parent_path',
        'ajax', 'minimum_input_length', 'inline_create', 'pivotSelect',
        'force_select', 'include_all_form_fields', 'method',
        'datetime_picker_options', 'date_picker_options',
    ];

    protected $map;

    public function __construct(?array $map = null)
    {
        $this->map = $map;
    }

    public function resolve(?array $field): array
    {
        $field = $field ?: [];

        $fieldType = $field['type'] ?? 'text';
        $map = $this->map ?? (array) config('backpack.settings.field_to_column_map', []);
        $columnType = $map[$fieldType] ?? 'text';

        // Harvest the picker's display format before stripping the picker option arrays.
        if (in_array($columnType, ['datetime', 'date'], true) && empty($field['format'])) {
            $displayFormat = $field['datetime_picker_options']['displayFormat']
                ?? $field['date_picker_options']['format']
                ?? null;

            if ($displayFormat) {
                $field['format'] = $displayFormat;
            }
        }

        $column = $field;
        foreach (self::STRIP_KEYS as $key) {
            unset($column[$key]);
        }

        $column['type'] = $columnType;
        $column['name'] = $field['name'] ?? 'value';

        return $column;
    }
}
