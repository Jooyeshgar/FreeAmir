<?php

namespace App\Services;

use App\Models\FiscalYear;
use Database\Seeders\BankSeeder;
use Database\Seeders\ConfigSeeder;
use Database\Seeders\CustomerGroupSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\OrganizationUnitSeeder;
use Database\Seeders\OrgChartSeeder;
use Database\Seeders\ProductGroupSeeder;
use Database\Seeders\ServiceGroupSeeder;
use Database\Seeders\SubjectSeeder;
use Illuminate\Support\Facades\DB;

class FiscalYearDemoService
{
    public function __construct(private readonly FiscalYearMutationGuard $mutationGuard) {}

    public function refresh(int $fiscalYearId): void
    {
        DB::transaction(function () use ($fiscalYearId): void {
            $year = FiscalYear::query()->lockForUpdate()->findOrFail($fiscalYearId);
            $this->mutationGuard->ensureOpen($year);
            $attributes = $year->getAttributes();
            // Closing progress refers to documents removed by the refresh.
            $attributes['pl_document_id'] = null;
            $attributes['closing_document_id'] = null;
            $attributes['closing_recalculation_step'] = null;
            $assignments = DB::table('fiscal_year_user')->where('fiscal_year_id', $year->id)->get();

            // Transfers restrict warehouse deletion; remove this year's transfers first.
            DB::table('warehouse_transfers')->where('fiscal_year_id', $year->id)->delete();
            DB::table('transactions')->whereIn('document_id', DB::table('documents')
                ->where('fiscal_year_id', $year->id)->select('id'))->delete();
            // Foreign keys remove owned records and their children without touching other years.
            DB::table('fiscal_years')->where('id', $year->id)->delete();
            DB::table('fiscal_years')->insert($attributes);
            foreach ($assignments as $assignment) {
                DB::table('fiscal_year_user')->insert((array) $assignment);
            }

            app(SubjectSeeder::class)->run($year->id);
            app(ConfigSeeder::class)->run($year->id, includeGlobalSettings: false);
            foreach ([BankSeeder::class, CustomerGroupSeeder::class,
                ProductGroupSeeder::class, ServiceGroupSeeder::class] as $seeder) {
                app($seeder)->run($year->id);
            }
            app(OrgChartSeeder::class)->run();
            app(OrganizationUnitSeeder::class)->run();
            app(DemoSeeder::class)->run($year->id);
        });
    }
}
