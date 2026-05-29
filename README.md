# Backpack\Settings

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Tests][ico-tests]][link-tests]
[![Coverage Status][ico-scrutinizer]][link-scrutinizer]
[![Quality Score][ico-code-quality]][link-code-quality]
[![Style CI](https://styleci.io/repos/53683729/shield)](https://styleci.io/repos/53683729)
[![Total Downloads][ico-downloads]][link-downloads]

An interface for the administrator to easily change application settings. Uses Laravel Backpack. Works on Backpack v4, v5 and v6.

> ### Security updates and breaking changes
> Please **[subscribe to the Backpack Newsletter](http://backpackforlaravel.com/newsletter)** so you can find out about any security updates, breaking changes or major features. We send an email every 1-2 months.

## Install

**Note:** The default table name is `settings`, if you need to change it please carefully read the comments in the instruction below.

In your terminal:

``` bash
# install the package
composer require backpack/settings

# [optional] if you need to change table name or migration name, please do it now before proceding
php artisan vendor:publish --provider="Backpack\Settings\SettingsServiceProvider" --tag="config"
# then change the values you need in in `config/backpack/settings.php`

# publish & run the migration
php artisan vendor:publish --provider="Backpack\Settings\SettingsServiceProvider"
php artisan migrate

# [optional] add a menu item for it
# For Backpack v6
php artisan backpack:add-menu-content "<x-backpack::menu-item title='Settings' icon='la la-cog' :link=\"backpack_url('setting')\" />"
# For Backpack v5 or v4
php artisan backpack:add-sidebar-content "<li class='nav-item'><a class='nav-link' href='{{ backpack_url('setting') }}'><i class='nav-icon la la-cog'></i> <span>Settings</span></a></li>"

# [optional] insert some example dummy data to the database
php artisan db:seed --class="Backpack\Settings\database\seeds\SettingsTableSeeder"
```

## Usage

### End user
Add it to the menu or access it by its route: **application/admin/setting**

### Programmer
Use it like you would any config value in a virtual settings.php file. Except the values are stored in the database and fetched on boot, instead of being stored in a file.

``` php
Setting::get('contact_email')
// or
Config::get('settings.contact_email')
```

### Add new settings

Settings are stored in the database in the "settings" table. Its columns are:
- id (ex: 1)
- key (ex: contact_email)
- name (ex: Contact form email address)
- description (ex: The email address that all emails go to.)
- value (ex: admin@laravelbackpack.com)
- field (Backpack CRUD field configuration in JSON format. The "name" of the field is **mandatory** to be "value") - see the field types and their configuration code on https://backpackforlaravel.com/docs/crud-fields#default-field-types
- active (1 or 0)
- created_at
- updated_at

There is no interface available to add new settings. They are added by the developer directly in the database, since the Backpack CRUD field configuration is a bit complicated. See the field types and their configuration code on https://backpackforlaravel.com/docs

### Override existing configurations

You can use this addon to make various Laravel configurations adjustable through the settings GUI, including Backpack settings themself.
For example, you can override the Backpack `show_powered_by` setting in `/config/backpack/ui.php`.

1. Create the setting entry in your settings database. You can add the settings manually, or via [Laravel seeders](https://laravel.com/docs/seeding). The values inserted into the database should be look similar to below:

   For Backpack `show_powered_by` setting:

   | Field | Value |
   | --- | --- |
   | key | show_powered_by |
   | name | Showed Powered By |
   | description | Whether to show the powered by Backpack on the bottom right corner or not. |
   | value | 1 |
   | field | {"name":"value","label":"Value","type":"checkbox"} |
   | active | 1 |
   
**NOTE**: The `field` column should be a JSON string. The `name` key in the JSON string should be `value`. Using any other key will not work.

3. Open up the `app/Providers/AppServiceProvider` file, and add the below lines:

   ```diff
   <?php

   namespace App\Providers;

   use Illuminate\Support\ServiceProvider;

   class AppServiceProvider extends ServiceProvider
   {
       /**
        * Bootstrap any application services.
        *
        * @return void
        */
       public function boot()
       {
   +       $this->overrideConfigValues();
       }

       /**
        * Register any application services.
        *
        * @return void
        */
       public function register()
       {
           //
       }

   +   protected function overrideConfigValues()
   +   {
   +       $config = [];
   +       if (config('settings.show_powered_by')) {
   +           $config['backpack.ui.show_powered_by'] = config('settings.show_powered_by') == '1';
   +       }
   +       config($config);
   +   }
   }
   ```

### Formatting values in the list view

The `field` JSON describes how a setting is **edited**. Backpack also needs to know how each setting's value should be **displayed** in the list (the `value` column). The package gives you two ways to control this:

- **Per-row explicit override** via the optional `column` DB field — always honored, no global config needed.
- **Automatic resolution from the field** via `field_to_column_map` — opt-in via the `auto_resolve_columns` config flag (off by default for backward compatibility; will default to `true` in the next major version).

Resolution order for each row:

1. **Explicit `column` definition** (recommended for full control) — if the row's `column` DB field contains a JSON column definition, it's used as-is. This works whether or not `auto_resolve_columns` is enabled.
2. **Derived from `field`** (only when `auto_resolve_columns` is `true`) — the package translates the row's `field` JSON into a matching column definition using a configurable map (e.g. `datetime` field → `datetime` column, `checkbox` → `boolean`, `select_from_array` → `select_from_array`, `upload` → `upload`, `repeatable` → `repeatable`, etc.). Every key from the field definition is forwarded to the column **except** a small blacklist of form-only keys (`attributes`, `wrapperAttributes`, `hint`, `placeholder`, `validation`/`validationRules`/`validationMessages`, `tab`, `fake`, `store_in`, `dependencies`, `on_change`, `view_namespace`, `inline_create`, `ajax`, `minimum_input_length`, `pivotSelect`, `force_select`, `datetime_picker_options`, `date_picker_options`, `readonly`, `disabled`, `autocomplete`, `allows_null`, `allows_multiple`, and similar). Column-specific keys like `temporary`, `expiration`, `height`, `width`, `radius`, `subfields`, `entity`, `model`, `attribute`, `relation_type`, `pivot`, `key`, `prefix`, `disk`, `withFiles`, `withMedia` — present or future — flow through automatically.
3. **Fallback** — plain text (the legacy behavior).

#### Enabling auto-resolution

In `config/backpack/settings.php`:

```php
'auto_resolve_columns' => true,
```

After enabling it, a datetime setting with a custom format

```
| Field  | Value                                                                                |
| key    | last_incident                                                                        |
| field  | {"name":"value","label":"Last Incident","type":"datetime","format":"M/D/YY h:mm A"}   |
| value  | 2026-05-08T11:00                                                                     |
```

will render as `5/8/26 11:00 AM` in the list, with no further configuration.

#### Per-row explicit override

You can store a column definition on any setting row, independently of the global flag — useful when you want a different display than what the field implies:

```
| Field  | Value                                                                                                  |
| field  | {"name":"value","label":"API token","type":"text"}                                                     |
| column | {"name":"value","label":"API token","type":"text","limit":12,"prefix":"\u2026"}                        |
| value  | sk-live-1234567890abcdef                                                                               |
```

This requires the optional `column` DB field (see Upgrading section below for existing installations).

#### Customizing the field-to-column map

The mapping table lives in `config/backpack/settings.php` under `field_to_column_map`. Add or override entries to support custom field types or change how a type is rendered, without forking the package:

```php
'field_to_column_map' => [
    // ...defaults...
    'my_custom_field' => 'my_custom_column',
    'tinymce'         => 'custom_html', // override default
],
```

#### Upload columns with `withFiles` / `withMedia`

Backpack's `upload`, `upload_multiple` and `image` columns can be configured with `->withFiles([...])` or `->withMedia([...])` to delegate path resolution to Backpack's uploaders (or to Spatie's Media Library, via [backpack/medialibrary-uploaders](https://github.com/Laravel-Backpack/medialibrary-uploaders)). In a normal CRUD this works because `setupListOperation()` calls the macro on a `CrudColumn` instance, which registers a `Model::retrieved` event that hydrates raw stored data (a path / a JSON array / a media id) into the URL the column actually displays.

In Settings every row shares the same `Setting` model and the same `value` attribute, so a global retrieved event can't be used — the last uploader would win for every row. Instead, this package hydrates upload values **per row, on demand**, right before the cell is rendered. Put a column definition like this on the row (either via the `column` DB field or via auto-resolution of a `withFiles`-configured field):

```json
{
  "name": "value",
  "type": "upload",
  "withFiles": { "disk": "public", "path": "settings" }
}
```

The package will:
- look up the right uploader class via Backpack's `UploadersRepository` (or honor an explicit `uploader` key on the definition),
- run its `retrieveUploadedFiles()` against the current setting row only,
- then render the upload column view with the resolved value.

This works for `withFiles`, `withMedia` and any custom uploader registered with the repository. If neither macro is present, the upload column falls back to its default behavior (treat `value` as a raw path on `disk`).

### Upgrading from earlier versions

**This release is fully backward compatible by default.** If you simply update the package, your existing settings list looks and behaves exactly as before — every value cell still renders as raw text, and no migration is required.

To opt into the new features:

1. Publish the new config (or hand-merge the `auto_resolve_columns`, `field_to_column_map` and `column_migration_name` keys):

   ```bash
   php artisan vendor:publish --provider="Backpack\Settings\SettingsServiceProvider" --tag="config"
   ```

2. To enable automatic per-type formatting in the list, set:

   ```php
   'auto_resolve_columns' => true,
   ```

3. (Optional) To use per-row explicit `column` overrides, publish and run the additive migration that adds the nullable `column` text field:

   ```bash
   php artisan vendor:publish --provider="Backpack\Settings\SettingsServiceProvider" --tag="migrations"
   php artisan migrate
   ```

   This migration is fully additive — the package works without it. Without it, only auto-resolution (and the legacy plain-text fallback) is available.

In the next major version, `auto_resolve_columns` will default to `true`.

## Screenshots

See [backpackforlaravel.com](https://backpackforlaravel.com)

- List view:
![List / table view in Backpack/Settings](https://user-images.githubusercontent.com/1032474/111115626-8f7a0480-856d-11eb-99bb-3004ec621ebb.gif)
- Editing a setting with the email field type:

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Testing

``` bash
$ composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Overwriting Functionality

If you need to modify how this works in a project:
- create a ```routes/backpack/settings.php``` file; the package will see that, and load _your_ routes file, instead of the one in the package;
- create controllers/models that extend the ones in the package, and use those in your new routes file;
- modify anything you'd like in the new controllers/models;

## Security

If you discover any security related issues, please email tabacitu@backpackforlaravel.com instead of using the issue tracker.

Please **[subscribe to the Backpack Newsletter](http://backpackforlaravel.com/newsletter)** so you can find out about any security updates, breaking changes or major features. We send an email every 1-2 months.

## Credits

- [Cristian Tabacitu][link-author]
- [All Contributors][link-contributors]

## License

Backpack is free for non-commercial use and 69 EUR/project for commercial use. Please see [License File](LICENSE.md) and [backpackforlaravel.com](https://backpackforlaravel.com/pricing) for more information.

## Hire us

We've spend more than 50.000 hours creating, polishing and maintaining administration panels on Laravel. We've developed e-Commerce, e-Learning, ERPs, social networks, payment gateways and much more. We've worked on admin panels _so much_, that we've created one of the most popular software in its niche - just from making public what was repetitive in our projects.

If you are looking for a developer/team to help you build an admin panel on Laravel, look no further. You'll have a difficult time finding someone with more experience & enthusiasm for this. This is _what we do_. [Contact us](https://backpackforlaravel.com/need-freelancer-or-development-team). Let's see if we can work together.


[ico-version]: https://img.shields.io/packagist/v/backpack/settings.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-dual-blue?style=flat-square
[ico-tests]: https://img.shields.io/github/actions/workflow/status/Laravel-Backpack/Settings/tests.yml?branch=master&label=tests&style=flat-square
[ico-scrutinizer]: https://img.shields.io/scrutinizer/coverage/g/laravel-backpack/settings.svg?style=flat-square
[ico-code-quality]: https://img.shields.io/scrutinizer/g/laravel-backpack/settings.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/backpack/settings.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/backpack/settings
[link-tests]: https://github.com/Laravel-Backpack/Settings/actions/workflows/tests.yml
[link-scrutinizer]: https://scrutinizer-ci.com/g/laravel-backpack/settings/code-structure
[link-code-quality]: https://scrutinizer-ci.com/g/laravel-backpack/settings
[link-downloads]: https://packagist.org/packages/backpack/settings
[link-author]: http://tabacitu.ro
[link-contributors]: ../../contributors
