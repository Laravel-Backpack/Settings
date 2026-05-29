<?php

namespace Backpack\Settings\app\Library;

/**
 * Translates a Backpack field definition (used to edit a setting on the
 * Update page) into a Backpack column definition (used to display the
 * setting's value on the List page).
 *
 * This is a thin, declarative mapper. It does not format values itself —
 * it produces a column definition array that Backpack's column views can
 * render natively, so every existing and future column type "just works".
 *
 * Strategy: blacklist form-only keys, pass everything else through.
 *
 * Why a blacklist instead of a whitelist? Each Backpack column type has
 * its own set of accepted attributes (`upload` uses `disk`, `prefix`,
 * `temporary`, `expiration`; `image` adds `height`, `width`, `radius`;
 * `repeatable` uses `subfields`; relationship columns add `entity`,
 * `model`, `attribute`, `relation_type`, `pivot`, `key`; new column types
 * appear in every Backpack release). Maintaining a whitelist of every
 * column attribute is fragile. The set of *form-only* keys, however, is
 * tied to Laravel form / validation mechanics and to Backpack's form
 * builder — it's a small, stable list. So we strip the bad keys and let
 * the rest flow through.
 */
class FieldToColumnResolver
{
    /**
     * Keys that exist on field definitions but have no meaning — or worse,
     * harmful meaning — on column definitions. Stripped before forwarding.
     *
     * Categories covered:
     *  - HTML form mechanics:           attributes, wrapperAttributes,
     *                                   placeholder, hint, readonly,
     *                                   disabled, autocomplete
     *  - Laravel validation:            validation, validationRules,
     *                                   validationMessages, allows_null,
     *                                   allows_multiple
     *  - Backpack form layout / state:  tab, fake, store_in, dependencies,
     *                                   on_change, new_item_label
     *  - Custom view dispatch:          view_namespace (points at FIELD
     *                                   views; if leaked, the column slot
     *                                   would try to render a field view)
     *  - Field-builder helpers:         field_unique_name, parent_field,
     *                                   parentFieldName, container_name
     *  - Pro / Ajax / Inline-create:    ajax, minimum_input_length,
     *                                   inline_create, pivotSelect,
     *                                   force_select, include_all_form_fields
     *
     * Notably NOT stripped: `withFiles` and `withMedia`. These are macros
     * registered on BOTH CrudField and CrudColumn (see
     * backpack/crud/src/macros.php and backpack/medialibrary-uploaders).
     * They store the upload definition and register the events that
     * hydrate stored values for display, so they must flow through to the
     * column or upload values won't render correctly.
     */
    protected const STRIP_KEYS = [
        // HTML form mechanics
        'attributes', 'wrapperAttributes', 'placeholder', 'hint',
        'readonly', 'disabled', 'autocomplete',

        // Laravel validation
        'validation', 'validationRules', 'validationMessages',
        'allows_null', 'allows_multiple',

        // Backpack form layout / state
        'tab', 'fake', 'store_in', 'dependencies', 'on_change',
        'new_item_label',

        // Custom view dispatch (CRITICAL: points at field views)
        'view_namespace',

        // Field-builder helpers (set at runtime, never useful on columns)
        'field_unique_name', 'parent_field', 'parentFieldName',
        'container_name', 'show_asterisk', 'parent_path',

        // Pro: ajax / inline-create / relationship form helpers
        'ajax', 'minimum_input_length', 'inline_create', 'pivotSelect',
        'force_select', 'include_all_form_fields', 'method',

        // *_picker form-config arrays (relevant displayFormat is harvested
        // separately into `format` before stripping)
        'datetime_picker_options', 'date_picker_options',
    ];

    /**
     * Field-type -> column-type map. When null, the resolver will read
     * `backpack.settings.field_to_column_map` from the Laravel config.
     *
     * @var array|null
     */
    protected $map;

    public function __construct(?array $map = null)
    {
        $this->map = $map;
    }

    /**
     * Build a column definition from a field definition.
     *
     * @param  array|null  $field  The decoded `field` JSON of a setting row.
     * @return array  A column definition suitable for CRUD::addColumn().
     */
    public function resolve(?array $field): array
    {
        $field = $field ?: [];

        $fieldType = $field['type'] ?? 'text';
        $map = $this->map ?? (array) config('backpack.settings.field_to_column_map', []);
        $columnType = $map[$fieldType] ?? 'text';

        // datetime/date columns: harvest the picker's display format into
        // `format` BEFORE stripping the picker option arrays.
        if (in_array($columnType, ['datetime', 'date'], true) && empty($field['format'])) {
            $displayFormat = $field['datetime_picker_options']['displayFormat']
                ?? $field['date_picker_options']['format']
                ?? null;

            if ($displayFormat) {
                $field['format'] = $displayFormat;
            }
        }

        // start from the full field definition, minus form-only keys
        $column = $field;
        foreach (self::STRIP_KEYS as $key) {
            unset($column[$key]);
        }

        // overwrite type, ensure name defaults to 'value'
        $column['type'] = $columnType;
        $column['name'] = $field['name'] ?? 'value';

        return $column;
    }
}
