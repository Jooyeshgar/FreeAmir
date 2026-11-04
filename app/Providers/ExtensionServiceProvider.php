<?php

namespace App\Providers;

use App\Extensions\Contracts\HostContext;
use App\Extensions\FreeAmirHostContext;
use App\Extensions\NavigationRegistry;
use Illuminate\Support\ServiceProvider;

class ExtensionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NavigationRegistry::class);
        $this->app->bind(HostContext::class, FreeAmirHostContext::class);
    }
}
