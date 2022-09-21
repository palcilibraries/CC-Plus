<?php

namespace App\Providers;
use \App\GlobalSetting;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Support\ServiceProvider;

class GlobalSettingsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap the application services.
     *
     * @param \Illuminate\Contracts\Cache\Factory $cache
     * @param \App\GlobalSetting $settings
     *
     * @return void
     */
    public function boot(Factory $cache, GlobalSetting $settings)
    {
        $settings = $cache->remember('ccplus', 60, function() use ($settings)
        {
            return $settings->pluck('value', 'name')->all();
        });
        config()->set('ccplus', $settings);
    }

}
