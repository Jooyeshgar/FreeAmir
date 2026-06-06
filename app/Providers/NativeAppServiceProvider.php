<?php

namespace App\Providers;

use Native\Desktop\Facades\Window;
use Native\Desktop\Contracts\ProvidesPhpIni;
use App\Models\Config;
use Illuminate\Support\Facades\Artisan;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has been booted.
     * Use this method to open windows, register global shortcuts, etc.
     */
    public function boot(): void
    {
        // if (Config::where('seeded', true)->count() !== 1) {
        //     Artisan::call('db:seed');
        // }
        try {
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);

        if (!\Illuminate\Support\Facades\Schema::hasTable('configs') || \App\Models\Config::where('seeded', true)->count() !== 1) {
            \Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
        }

        } catch (\Exception $e) {
        logger('Database error: ' . $e->getMessage());
        }

        Window::open()
        ->maximized() // Opens the window in full screen on launch
        ->minWidth(1024)
        ->minHeight(768)
        ->showDevTools(false)
        ->rememberState();
    }

    /**
     * Return an array of php.ini directives to be set.
     */
    public function phpIni(): array
    {
        return [
              'memory_limit' => '512M',
            'display_errors' => '1',
            'error_reporting' => 'E_ALL',
            'max_execution_time' => '0',
            'max_input_time' => '0',
        ];
    }
}
