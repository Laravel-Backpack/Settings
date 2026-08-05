<?php

namespace Backpack\Settings;

use Backpack\Settings\app\Models\Setting;
use Config;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    /**
     * Indicates if the loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Where the route file lives, both inside the package and in the app (if overwritten).
     *
     * @var string
     */
    public $routeFilePath = '/routes/backpack/settings.php';

    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        // use the vendor configuration file as fallback
        $this->mergeConfigFrom(
            __DIR__.'/config/backpack/settings.php',
            'backpack.settings'
        );

        // define the routes for the application
        $this->setupRoutes();

        // listen for settings to be saved and clear the cache when any Setting model is saved.
        Setting::saved(function () {
            Cache::forget('backpack_settings_cache');
        });

        // only use the Settings package if the Settings table is present in the database
        $tableExists = Cache::remember('backpack_settings_table_exists', config('backpack.settings.cache_time', 60), function () {
            return Schema::hasTable(config('backpack.settings.table_name'));
        });

        if (!App::runningInConsole() && $tableExists) {
            // get all settings from the database if they're not in the database.
            $settings = Cache::remember('backpack_settings_cache', config('backpack.settings.cache_time', 60), function () {
                /** @var \Illuminate\Database\Eloquent\Model $modelClass */
                $modelClass = config('backpack.settings.model', \Backpack\Settings\app\Models\Setting::class);

                // get all settings from the database
                $settings = $modelClass::all();
                return $settings;
            });

            $config_prefix = config('backpack.settings.config_prefix');

            // bind all settings to the Laravel config, so you can call them like
            // Config::get('settings.contact_email')
            foreach ($settings as $key => $setting) {
                $prefixed_key = !empty($config_prefix) ? $config_prefix.'.'.$setting->key : $setting->key;
                config([$prefixed_key => $setting->value]);
            }
        }
        // publish the migrations and seeds
        $this->publishes([
            __DIR__.'/database/migrations/create_settings_table.php.stub' => database_path('migrations/'.config('backpack.settings.migration_name').'.php'),
        ], 'migrations');

        // publish translation files
        $this->publishes([__DIR__.'/resources/lang' => app()->langPath().'/vendor/backpack'], 'lang');

        // publish setting files
        $this->publishes([__DIR__.'/config' => config_path()], 'config');
    }

    /**
     * Define the routes for the application.
     *
     * @return void
     */
    public function setupRoutes()
    {
        // by default, use the routes file provided in the vendor
        $routeFilePathInUse = __DIR__.$this->routeFilePath;

        // but if there's a file with the same name in routes/backpack, use that one
        if (file_exists(base_path().$this->routeFilePath)) {
            $routeFilePathInUse = base_path().$this->routeFilePath;
        }

        $this->loadRoutesFrom($routeFilePathInUse);
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
        // register their aliases
        $loader = \Illuminate\Foundation\AliasLoader::getInstance();
        $loader->alias('Setting', config('backpack.settings.model', \Backpack\Settings\app\Models\Setting::class));
    }
}
