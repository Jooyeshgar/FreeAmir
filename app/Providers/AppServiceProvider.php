<?php

namespace App\Providers;

use App\Faker\PersianProductProvider;
use App\Faker\PersianServiceProvider;
use App\Services\ActivityLogService;
use Faker\Generator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\PermissionRegistrar;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(ActivityLogService::class);

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
        app(PermissionRegistrar::class)->setPermissionsTeamId(0);

        Paginator::defaultView('vendor.pagination.daisyui');
        Paginator::defaultSimpleView('vendor.pagination.daisyui-simple');

        App::setLocale(config('app.locale', 'fa'));

        Gate::before(function ($user, $ability) {
            static $globalSuperAdmins = [];

            $isGlobalSuperAdmin = $globalSuperAdmins[$user->getKey()] ??= DB::table(config('permission.table_names.model_has_roles'))
                ->join(
                    config('permission.table_names.roles'),
                    config('permission.table_names.roles').'.id',
                    '=',
                    config('permission.table_names.model_has_roles').'.role_id'
                )
                ->where(config('permission.table_names.model_has_roles').'.company_id', 0)
                ->where(config('permission.table_names.model_has_roles').'.model_type', $user->getMorphClass())
                ->where(config('permission.table_names.model_has_roles').'.model_id', $user->getKey())
                ->where(config('permission.table_names.roles').'.name', 'Super-Admin')
                ->exists();

            if ($isGlobalSuperAdmin || $user->hasRole('Super-Admin')) {
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
