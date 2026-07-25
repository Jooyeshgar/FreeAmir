<?php

namespace App\Providers;

use App\Faker\PersianProductProvider;
use App\Faker\PersianServiceProvider;
use App\Services\ActivityLogService;
use Faker\Generator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Event;
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

        foreach (['created', 'updated', 'deleted'] as $modelEvent) {
            Event::listen("eloquent.{$modelEvent}: *", function (string $eventName, array $payload) use ($modelEvent): void {
                if (isset($payload[0]) && $payload[0] instanceof Model) {
                    app(ActivityLogService::class)->recordModelEvent($modelEvent, $payload[0]);
                }
            });
        }
    }
}
