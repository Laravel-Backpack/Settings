<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Table Name
    |--------------------------------------------------------------------------
    |
    | Database Settings Table Name
    |
    */
    'table_name' => 'settings',

    /*
    |--------------------------------------------------------------------------
    | Model Name
    |--------------------------------------------------------------------------
    |
    | Settings Eloquent Model Class
    |
    */
    'model' => \Backpack\Settings\app\Models\Setting::class,

    /*
    |--------------------------------------------------------------------------
    | Route
    |--------------------------------------------------------------------------
    |
    | URL Segment aka route to the Settings panel.
    |
    */
    'route' => 'setting',

    /*
    |--------------------------------------------------------------------------
    | Config Prefix
    |--------------------------------------------------------------------------
    |
    | The prefix used to add your settings into the configuration array.
    | With this default you can grab your settings with config('settings.your_setting_key')
    |
    | WARNING: WE ADVISE TO NOT LEAVE THIS EMPTY / CHECK IF IT DOES NOT CONFLICT WITH OTHER CONFIG FILE NAMES
    |
    |   - if you leave this empty and your keys match other configuration files you might overwrite them.
    |
    */
    'config_prefix' => 'settings',

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Settings are loaded from the database on every request and bound to
    | the Laravel config. To avoid hitting the DB on each request, the
    | result is cached. The cache is automatically invalidated whenever a
    | setting row is saved or deleted, so the TTL is just a safety net.
    |
    | Set `enabled` to false to disable caching entirely (useful in dev).
    | Set `store` to null to use the default cache store, or to a specific
    | store name (e.g. 'redis', 'file', 'array') from config/cache.php.
    | `ttl` is in seconds; default is 30 days.
    |
    */
    'cache' => [
        'enabled' => true,
        'store'   => null,
        'key'     => 'backpack.settings.all',
        'ttl'     => 60 * 60 * 24 * 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Migration file name
    |--------------------------------------------------------------------------
    |
    | The file name for the settings migration file. It will be created in database_path('/migrations/%name_you_choose%.php)
    | Note: .php extension is automatically added.
    |
    */
    'migration_name' => '2015_08_04_131614_create_settings_table',

    /*
    |--------------------------------------------------------------------------
    | Column Migration file name
    |--------------------------------------------------------------------------
    |
    | The file name for the migration that adds the optional `column` text
    | field to the settings table. It allows defining how each setting's
    | value is displayed in the list view, independent from how it's edited.
    |
    */
    'column_migration_name' => '2026_05_29_000000_add_column_to_settings_table',

    /*
    |--------------------------------------------------------------------------
    | Auto-resolve list columns from field definition
    |--------------------------------------------------------------------------
    |
    | When `true`, the list view will format each setting's value cell
    | according to the row's `field` JSON type (datetime fields render as
    | formatted dates, checkbox fields as boolean icons, etc.) using the
    | `field_to_column_map` below.
    |
    | When `false` (default), the value cell shows the raw stored value
    | as plain text — this matches the legacy behavior of this package
    | and is the safe default for upgrades, since auto-resolution can
    | change the visual appearance of existing settings lists.
    |
    | Regardless of this flag, a setting row may always opt in on its own
    | by storing an explicit column definition in its `column` DB field;
    | that definition takes precedence over both auto-resolution and the
    | plain-text fallback.
    |
    | This will default to `true` in the next major version.
    |
    */
    'auto_resolve_columns' => false,

    /*
    |--------------------------------------------------------------------------
    | Field-to-Column Type Map
    |--------------------------------------------------------------------------
    |
    | When a setting row has no explicit `column` definition, the Settings
    | package derives one from its `field` definition. This map controls how
    | each Backpack field type is translated into a list-view column type.
    |
    | Set a type to `null` to fall back to the default ('text'). Add your
    | own entries here to support custom field types or override the
    | built-in defaults without forking the package.
    |
    */
    'field_to_column_map' => [
        // dates
        'date'             => 'date',
        'date_picker'      => 'date',
        'datetime'         => 'datetime',
        'datetime_picker'  => 'datetime',
        'time'             => 'time',
        'week'             => 'week',
        'month'            => 'month',

        // booleans
        'checkbox'         => 'boolean',
        'boolean'          => 'boolean',
        'switch'           => 'boolean',

        // selects
        'select_from_array'        => 'select_from_array',
        'enum'                     => 'enum',
        'radio'                    => 'radio',
        'select'                   => 'select',
        'select2'                  => 'select',
        'select_multiple'          => 'select_multiple',
        'select2_multiple'         => 'select_multiple',
        'select2_from_array'       => 'select_from_array',
        'checklist'                => 'checklist',

        // relationships (pro)
        'relationship'             => 'relationship',
        'repeatable'               => 'repeatable',

        // media
        'upload'           => 'upload',
        'upload_multiple'  => 'upload_multiple',
        'image'            => 'image',
        'browse'           => 'upload',
        'browse_multiple'  => 'upload_multiple',

        // misc
        'color'            => 'color',
        'color_picker'     => 'color',
        'number'           => 'number',
        'range'            => 'number',
        'email'            => 'email',
        'url'              => 'url',
        'phone'            => 'phone',
        'password'         => 'password',
        'textarea'         => 'textarea',
        'tinymce'          => 'textarea',
        'ckeditor'         => 'textarea',
        'summernote'       => 'textarea',
        'easymde'          => 'textarea',
        'markdown'         => 'textarea',
        'custom_html'      => 'custom_html',
        'hidden'           => 'text',
        'text'             => 'text',
    ],
];
