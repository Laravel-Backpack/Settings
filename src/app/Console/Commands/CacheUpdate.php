<?php

namespace Backpack\Settings\app\Console\Commands;

use Artisan;
use Backpack\Settings\app\Models\Setting;
use Config;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CacheUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backpack_settings:cache_update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update Settings Cache Manually';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        /** @var \Illuminate\Database\Eloquent\Model $modelClass */
        $modelClass = config('backpack.settings.model', \Backpack\Settings\app\Models\Setting::class);

        // get all settings from the database
        $settings = $modelClass::all();
        
        Cache::put('backpack_settings_cache', config('backpack.settings.cache_time', 60), $settings);

    }

}
