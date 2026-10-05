<?php

namespace App\Providers;

use App\Faker\PersianProductProvider;
use App\Faker\PersianServiceProvider;
use App\Models\AncillaryCost;
use App\Models\AttendanceLog;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Cheque;
use App\Models\Chequebook;
use App\Models\CommercialLedgerExport;
use App\Models\Config;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Document;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\MonthlyAttendance;
use App\Models\MonthlyBudget;
use App\Models\OrganizationUnit;
use App\Models\OrgChart;
use App\Models\Payroll;
use App\Models\PayrollElement;
use App\Models\PersonnelRequest;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\PublicHoliday;
use App\Models\SalaryDecree;
use App\Models\Service;
use App\Models\ServiceGroup;
use App\Models\Subject;
use App\Models\TaxSlab;
use App\Models\Warehouse;
use App\Models\WarehouseTransfer;
use App\Models\WorkShift;
use App\Models\WorkSite;
use App\Observers\FiscalYearAssignmentObserver;
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
        Paginator::defaultView('vendor.pagination.daisyui');
        Paginator::defaultSimpleView('vendor.pagination.daisyui-simple');

        foreach ([
            AncillaryCost::class,
            AttendanceLog::class,
            Bank::class,
            BankAccount::class,
            Cheque::class,
            Chequebook::class,
            CommercialLedgerExport::class,
            Config::class,
            Customer::class,
            CustomerGroup::class,
            Document::class,
            Employee::class,
            Invoice::class,
            MonthlyAttendance::class,
            MonthlyBudget::class,
            OrgChart::class,
            OrganizationUnit::class,
            Payroll::class,
            PayrollElement::class,
            PersonnelRequest::class,
            Product::class,
            ProductGroup::class,
            PublicHoliday::class,
            SalaryDecree::class,
            Service::class,
            ServiceGroup::class,
            Subject::class,
            TaxSlab::class,
            Warehouse::class,
            WarehouseTransfer::class,
            WorkShift::class,
            WorkSite::class,
        ] as $modelClass) {
            $modelClass::observe(FiscalYearAssignmentObserver::class);
        }

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
