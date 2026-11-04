<?php

use App\Providers\AppServiceProvider;
use App\Providers\CoreNavigationServiceProvider;
use App\Providers\ExtensionServiceProvider;

return [
    AppServiceProvider::class,
    ExtensionServiceProvider::class,
    CoreNavigationServiceProvider::class,
];
