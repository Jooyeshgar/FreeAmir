<?php

namespace App\Providers;

use App\Extensions\Contracts\HostContext;
use App\Extensions\FreeAmirHostContext;
use App\Extensions\InvoiceExtensionRegistry;
use App\Extensions\NavigationRegistry;
use App\Extensions\ViewSlotRegistry;
use Illuminate\Support\ServiceProvider;

class ExtensionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NavigationRegistry::class);
        $this->app->singleton(ViewSlotRegistry::class);
        $this->app->singleton(InvoiceExtensionRegistry::class);
        $this->app->bind(HostContext::class, FreeAmirHostContext::class);
    }
}
