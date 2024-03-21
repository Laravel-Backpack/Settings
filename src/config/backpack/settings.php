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
    | Migration file name
    |--------------------------------------------------------------------------
    |
    | The file name for the settings migration file. It will be created in database_path('/migrations/%name_you_choose%.php)
    | Note: .php extension is automatically added.
    |
    */
    'migration_name' => '2015_08_04_131614_create_settings_table',
];
