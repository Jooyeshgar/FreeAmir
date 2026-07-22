<?php

namespace App\Providers;

use App\Faker\PersianProductProvider;
use App\Faker\PersianServiceProvider;
use Faker\Generator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->afterResolving(Generator::class, function (Generator $faker) {
            $registered = [];
            foreach ($faker->getProviders() as $provider) {
                $registered[get_class($provider)] = true;
            }

            if (! isset($registered[PersianProductProvider::class])) {
                $faker->addProvider(new PersianProductProvider($faker));
            }

            if (! isset($registered[PersianServiceProvider::class])) {
                $faker->addProvider(new PersianServiceProvider($faker));
            }
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::defaultView('vendor.pagination.daisyui');
        Paginator::defaultSimpleView('vendor.pagination.daisyui-simple');

        App::setLocale(config('app.locale', 'fa'));

        Gate::before(function ($user, $ability) {
            if ($user->hasRole('Super-Admin')) {
                return true;
            }
        });

    }
}
