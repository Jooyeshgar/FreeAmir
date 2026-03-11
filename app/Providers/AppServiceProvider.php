<?php

namespace App\Providers;

use App\Faker\PersianProductProvider;
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
        $this->app->afterResolving(\Faker\Generator::class, function (\Faker\Generator $faker) {
            foreach ($faker->getProviders() as $provider) {
                if ($provider instanceof PersianProductProvider) {
                    return;
                }
            }
            $faker->addProvider(new PersianProductProvider($faker));
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
