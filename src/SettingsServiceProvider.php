<?php

namespace Backpack\Settings;

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

        $this->loadViewsFrom(__DIR__.'/resources/views', 'backpack-settings');

        // only use the Settings package if the Settings table is present in the database
        if (!App::runningInConsole() && Schema::hasTable(config('backpack.settings.table_name'))) {
            /** @var \Illuminate\Database\Eloquent\Model $modelClass */
            $modelClass = config('backpack.settings.model', \Backpack\Settings\app\Models\Setting::class);

            $settings = $this->getCachedSettings($modelClass);

            $config_prefix = config('backpack.settings.config_prefix');

            // bind all settings to the Laravel config, so you can call them like
            // Config::get('settings.contact_email')
            foreach ($settings as $key => $setting) {
                $prefixed_key = !empty($config_prefix) ? $config_prefix.'.'.$setting['key'] : $setting['key'];
                config([$prefixed_key => $setting['value']]);
            }
        }
        // publish the migrations and seeds
        $this->publishes([
            __DIR__.'/database/migrations/create_settings_table.php.stub'        => database_path('migrations/'.config('backpack.settings.migration_name').'.php'),
            __DIR__.'/database/migrations/add_column_to_settings_table.php.stub' => database_path('migrations/'.config('backpack.settings.column_migration_name', '2026_05_29_000000_add_column_to_settings_table').'.php'),
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

    /**
     * Load all settings, using a cached array when caching is enabled.
     * Returns an array of ['key' => ..., 'value' => ...] entries.
     */
    protected function getCachedSettings(string $modelClass): array
    {
        $loader = fn () => $modelClass::all(['key', 'value'])
            ->map(fn ($s) => ['key' => $s->key, 'value' => $s->value])
            ->all();

        if (!config('backpack.settings.cache.enabled', true)) {
            return $loader();
        }

        $store = config('backpack.settings.cache.store');
        $key = config('backpack.settings.cache.key', 'backpack.settings.all');
        $ttl = (int) config('backpack.settings.cache.ttl', 60 * 60 * 24 * 30);

        return Cache::store($store)->remember($key, $ttl, $loader);
    }

    /**
     * Forget the cached settings payload. Called from the Setting model on
     * saved/deleted events so changes are picked up on the next request.
     */
    public static function forgetCache(): void
    {
        if (!config('backpack.settings.cache.enabled', true)) {
            return;
        }

        Cache::store(config('backpack.settings.cache.store'))
            ->forget(config('backpack.settings.cache.key', 'backpack.settings.all'));
    }
}
