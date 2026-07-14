<?php

namespace Backpack\Settings\Test;

use Backpack\Settings\app\Library\FieldToColumnResolver;
use PHPUnit\Framework\TestCase;

class FieldToColumnResolverTest extends TestCase
{
    /**
     * Default field-to-column map used in tests. Mirrors the package's
     * shipped config so tests are independent from Laravel's container.
     */
    protected function map(): array
    {
        return [
            'date'              => 'date',
            'date_picker'       => 'date',
            'datetime'          => 'datetime',
            'datetime_picker'   => 'datetime',
            'time'              => 'time',
            'checkbox'          => 'boolean',
            'switch'            => 'boolean',
            'select_from_array' => 'select_from_array',
            'select'            => 'select',
            'upload'            => 'upload',
            'image'             => 'image',
            'color'             => 'color',
            'number'            => 'number',
            'email'             => 'email',
            'url'               => 'url',
            'textarea'          => 'textarea',
            'tinymce'           => 'textarea',
            'text'              => 'text',
        ];
    }

    protected function resolver(): FieldToColumnResolver
    {
        return new FieldToColumnResolver($this->map());
    }

    public function test_it_defaults_to_text_when_field_is_null()
    {
        $column = $this->resolver()->resolve(null);

        $this->assertSame('value', $column['name']);
        $this->assertSame('text', $column['type']);
    }

    public function test_it_defaults_to_text_when_field_is_empty()
    {
        $column = $this->resolver()->resolve([]);

        $this->assertSame('text', $column['type']);
    }

    public function test_it_falls_back_to_text_for_unknown_field_types()
    {
        $column = $this->resolver()->resolve(['type' => 'totally_made_up']);

        $this->assertSame('text', $column['type']);
    }

    public function test_it_maps_datetime_field_to_datetime_column()
    {
        $column = $this->resolver()->resolve([
            'name'   => 'value',
            'label'  => 'Last Incident',
            'type'   => 'datetime',
            'format' => 'YYYY-MM-DD HH:mm:ss',
        ]);

        $this->assertSame('datetime', $column['type']);
        $this->assertSame('YYYY-MM-DD HH:mm:ss', $column['format']);
        $this->assertSame('Last Incident', $column['label']);
    }

    public function test_it_promotes_datetime_picker_display_format_to_column_format()
    {
        $column = $this->resolver()->resolve([
            'name'                    => 'value',
            'type'                    => 'datetime_picker',
            'datetime_picker_options' => [
                'displayFormat' => 'M/D/YY h:mm A',
                'format'        => 'YYYY-MM-DD HH:mm:ss',
            ],
        ]);

        $this->assertSame('datetime', $column['type']);
        $this->assertSame('M/D/YY h:mm A', $column['format']);
    }

    public function test_explicit_field_format_wins_over_picker_display_format()
    {
        $column = $this->resolver()->resolve([
            'name'                    => 'value',
            'type'                    => 'datetime_picker',
            'format'                  => 'DD/MM/YYYY',
            'datetime_picker_options' => ['displayFormat' => 'M/D/YY h:mm A'],
        ]);

        $this->assertSame('DD/MM/YYYY', $column['format']);
    }

    public function test_it_maps_checkbox_to_boolean()
    {
        $column = $this->resolver()->resolve(['type' => 'checkbox']);

        $this->assertSame('boolean', $column['type']);
    }

    public function test_it_forwards_options_for_select_from_array()
    {
        $column = $this->resolver()->resolve([
            'name'    => 'value',
            'type'    => 'select_from_array',
            'options' => ['a' => 'A', 'b' => 'B'],
        ]);

        $this->assertSame('select_from_array', $column['type']);
        $this->assertSame(['a' => 'A', 'b' => 'B'], $column['options']);
    }

    public function test_it_forwards_disk_for_upload()
    {
        $column = $this->resolver()->resolve([
            'name' => 'value',
            'type' => 'upload',
            'disk' => 'public',
        ]);

        $this->assertSame('upload', $column['type']);
        $this->assertSame('public', $column['disk']);
    }

    /**
     * The `upload` column also reads `temporary`, `expiration` and `prefix`.
     * Verify they all flow through.
     */
    public function test_it_forwards_all_upload_column_attributes()
    {
        $column = $this->resolver()->resolve([
            'name'       => 'value',
            'type'       => 'upload',
            'disk'       => 's3',
            'prefix'     => 'uploads/',
            'temporary'  => 30,
            'expiration' => 60,
        ]);

        $this->assertSame('s3', $column['disk']);
        $this->assertSame('uploads/', $column['prefix']);
        $this->assertSame(30, $column['temporary']);
        $this->assertSame(60, $column['expiration']);
    }

    /**
     * The `image` column uses `height`, `width`, `radius`, `prefix`,
     * `disk`, `temporary`, `expiration`. Verify they all flow through.
     */
    public function test_it_forwards_all_image_column_attributes()
    {
        $resolver = new FieldToColumnResolver($this->map() + ['image' => 'image']);

        $column = $resolver->resolve([
            'name'       => 'value',
            'type'       => 'image',
            'disk'       => 'public',
            'prefix'     => 'images/',
            'height'     => '50px',
            'width'      => '80px',
            'radius'     => '8px',
            'temporary'  => 10,
            'expiration' => 5,
        ]);

        $this->assertSame('image', $column['type']);
        $this->assertSame('50px', $column['height']);
        $this->assertSame('80px', $column['width']);
        $this->assertSame('8px', $column['radius']);
        $this->assertSame(10, $column['temporary']);
        $this->assertSame(5, $column['expiration']);
    }

    /**
     * `withFiles` and `withMedia` are uploader macros registered on BOTH
     * CrudField and CrudColumn (see backpack/crud/src/macros.php and
     * backpack/medialibrary-uploaders). They store the upload definition
     * and register the events that hydrate stored values for display, so
     * they MUST flow through to the column definition unchanged.
     */
    public function test_it_forwards_uploader_macros_to_column()
    {
        $withFiles = ['disk' => 'public', 'path' => 'settings'];
        $withMedia = ['disk' => 'media', 'collection' => 'settings'];

        $column = $this->resolver()->resolve([
            'name'      => 'value',
            'type'      => 'upload',
            'disk'      => 'public',
            'withFiles' => $withFiles,
            'withMedia' => $withMedia,
        ]);

        $this->assertSame($withFiles, $column['withFiles']);
        $this->assertSame($withMedia, $column['withMedia']);
        $this->assertSame('public', $column['disk']);
    }

    /**
     * Form-only keys (attributes, hint, view_namespace, tab, validation,
     * wrapperAttributes, fake, etc.) must NOT leak onto the column
     * definition, since they have different semantics there and would
     * break the list view (e.g. `attributes` is an HTML input attrs array
     * on fields, and `view_namespace` would point at a *field* view path).
     */
    public function test_it_strips_form_only_attributes()
    {
        $column = $this->resolver()->resolve([
            'name'                  => 'value',
            'type'                  => 'datetime',
            'format'                => 'YYYY-MM-DD',
            'attributes'            => ['class' => 'form-control-lg'],
            'wrapperAttributes'     => ['class' => 'col-md-6'],
            'hint'                  => 'Pick a date',
            'view_namespace'        => 'custom.fields',
            'tab'                   => 'Dates',
            'validation'            => 'required',
            'validationRules'       => 'required',
            'validationMessages'    => ['required' => 'nope'],
            'fake'                  => true,
            'store_in'              => 'extras',
            'dependencies'          => ['other_field'],
            'on_change'             => 'doSomething()',
            'inline_create'         => true,
            'ajax'                  => true,
            'minimum_input_length'  => 2,
            'placeholder'           => 'Pick one',
            'readonly'              => true,
            'disabled'              => true,
            'autocomplete'          => 'off',
            'allows_null'           => true,
            'allows_multiple'       => false,
            'pivotSelect'           => ['type' => 'select'],
            'force_select'          => true,
        ]);

        foreach ([
            'attributes', 'wrapperAttributes', 'hint', 'view_namespace',
            'tab', 'validation', 'validationRules', 'validationMessages',
            'fake', 'store_in', 'dependencies', 'on_change',
            'inline_create', 'ajax', 'minimum_input_length', 'placeholder',
            'readonly', 'disabled', 'autocomplete',
            'allows_null', 'allows_multiple', 'pivotSelect', 'force_select',
        ] as $k) {
            $this->assertArrayNotHasKey($k, $column, "Form-only key `$k` leaked onto column");
        }
    }

    public function test_it_strips_picker_options_after_harvesting_format()
    {
        $column = $this->resolver()->resolve([
            'name'                    => 'value',
            'type'                    => 'datetime_picker',
            'datetime_picker_options' => ['displayFormat' => 'M/D/YY h:mm A'],
        ]);

        $this->assertSame('M/D/YY h:mm A', $column['format']);
        $this->assertArrayNotHasKey('datetime_picker_options', $column);
    }

    /**
     * `subfields` IS a valid column attribute on `repeatable`, `relationship`
     * and `checklist_dependency` columns (used by Backpack PRO). It must be
     * forwarded to the column definition.
     */
    public function test_it_passes_through_subfields_for_repeatable_like_columns()
    {
        $subfields = [
            ['name' => 'street', 'type' => 'text'],
            ['name' => 'city',   'type' => 'text'],
        ];

        $resolver = new FieldToColumnResolver($this->map() + ['repeatable' => 'repeatable']);

        $column = $resolver->resolve([
            'name'      => 'value',
            'type'      => 'repeatable',
            'subfields' => $subfields,
        ]);

        $this->assertSame('repeatable', $column['type']);
        $this->assertSame($subfields, $column['subfields']);
    }

    public function test_it_passes_through_relationship_attributes()
    {
        $resolver = new FieldToColumnResolver($this->map() + ['relationship' => 'relationship']);

        $column = $resolver->resolve([
            'name'          => 'value',
            'type'          => 'relationship',
            'entity'        => 'category',
            'attribute'     => 'name',
            'model'         => 'App\\Models\\Category',
            'relation_type' => 'BelongsTo',
            'pivot'         => false,
            'key'           => 'category_id',
        ]);

        $this->assertSame('relationship', $column['type']);
        $this->assertSame('category', $column['entity']);
        $this->assertSame('name', $column['attribute']);
        $this->assertSame('App\\Models\\Category', $column['model']);
        $this->assertSame('BelongsTo', $column['relation_type']);
        $this->assertFalse($column['pivot']);
        $this->assertSame('category_id', $column['key']);
    }

    public function test_it_passes_through_safe_display_keys()
    {
        $column = $this->resolver()->resolve([
            'name'    => 'value',
            'type'    => 'text',
            'prefix'  => '$',
            'suffix'  => ' USD',
            'default' => 'n/a',
            'wrapper' => ['element' => 'a', 'href' => '#'],
            'escaped' => false,
            'limit'   => 100,
        ]);

        $this->assertSame('$', $column['prefix']);
        $this->assertSame(' USD', $column['suffix']);
        $this->assertSame('n/a', $column['default']);
        $this->assertSame(['element' => 'a', 'href' => '#'], $column['wrapper']);
        $this->assertFalse($column['escaped']);
        $this->assertSame(100, $column['limit']);
    }

    public function test_user_can_override_map_via_constructor()
    {
        $resolver = new FieldToColumnResolver([
            'my_custom_field' => 'my_custom_column',
        ]);

        $column = $resolver->resolve(['type' => 'my_custom_field']);

        $this->assertSame('my_custom_column', $column['type']);
    }

    /**
     * The blacklist approach means any field key NOT on the strip list flows
     * through. This is the whole point: future column types and per-type
     * attributes work automatically without us having to update a whitelist.
     */
    public function test_unknown_attributes_flow_through_to_column()
    {
        $column = $this->resolver()->resolve([
            'name'              => 'value',
            'type'              => 'text',
            'some_future_attr'  => 'foo',
            'another_one'       => ['nested' => true],
        ]);

        $this->assertSame('foo', $column['some_future_attr']);
        $this->assertSame(['nested' => true], $column['another_one']);
    }

    public function test_it_defaults_column_name_to_value_when_field_has_no_name()
    {
        $column = $this->resolver()->resolve(['type' => 'text']);

        $this->assertSame('value', $column['name']);
    }
}
