<?php

namespace App\Providers;

use App\Models\Config;
use Illuminate\Support\ServiceProvider;

class ConfigurationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot()
    {
        try {
            $configurations = Config::all();

            foreach ($configurations as $config) {
                config(['amir.' . $config->key => $config->value]);
            }
        } catch (\Exception $exception) {
            //
        }
    }
}
