<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('logo')->nullable();
            $table->string('address', 150)->nullable();
            $table->string('economical_code', 15)->nullable();
            $table->string('national_code', 12)->nullable();
            $table->string('postal_code')->nullable();
            $table->string('phone_number', 11)->nullable();
            $table->string('currency', 50)->default('Rial');
            $table->string('certificate_path')->nullable();
            $table->string('private_key_path')->nullable();
            $table->string('moadian_username', 20)->nullable();
            $table->string('tax_id', 20)->nullable();
        });

        $companyColumns = [
            'name', 'logo', 'address', 'economical_code', 'national_code',
            'postal_code', 'phone_number', 'currency', 'certificate_path',
            'private_key_path', 'moadian_username', 'tax_id',
        ];
        $companyIdsByName = [];
        $yearCompanyIds = [];

        // The first existing row for each exact name supplies shared fields.
        // Keep every old ID so all accounting foreign keys and user grants survive.
        foreach (DB::table('companies')->orderBy('id')->cursor() as $year) {
            $name = $year->name;

            if (! array_key_exists($name, $companyIdsByName)) {
                $companyIdsByName[$name] = DB::table('business_companies')->insertGetId(
                    array_intersect_key((array) $year, array_flip($companyColumns))
                );
            }

            $yearCompanyIds[$year->id] = $companyIdsByName[$name];
        }

        Schema::rename('companies', 'fiscal_years');
        Schema::rename('business_companies', 'companies');

        Schema::table('fiscal_years', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id');
        });

        foreach ($yearCompanyIds as $yearId => $companyId) {
            DB::table('fiscal_years')->where('id', $yearId)->update(['company_id' => $companyId]);
        }

        Schema::table('fiscal_years', function (Blueprint $table) use ($companyColumns) {
            $table->dropColumn($companyColumns);
        });

        Schema::table('fiscal_years', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable(false)->change();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->index(['company_id', 'fiscal_year']);
        });

        Schema::rename('company_user', 'fiscal_year_user');
        Schema::table('fiscal_year_user', function (Blueprint $table) {
            $table->renameColumn('company_id', 'fiscal_year_id');
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_year_user', function (Blueprint $table) {
            $table->renameColumn('fiscal_year_id', 'company_id');
        });
        Schema::rename('fiscal_year_user', 'company_user');

        Schema::table('fiscal_years', function (Blueprint $table) {
            $table->string('name', 50)->nullable();
            $table->string('logo')->nullable();
            $table->string('address', 150)->nullable();
            $table->string('economical_code', 15)->nullable();
            $table->string('national_code', 12)->nullable();
            $table->string('postal_code')->nullable();
            $table->string('phone_number', 11)->nullable();
            $table->string('currency', 50)->nullable();
            $table->string('certificate_path')->nullable();
            $table->string('private_key_path')->nullable();
            $table->string('moadian_username', 20)->nullable();
            $table->string('tax_id', 20)->nullable();
        });

        foreach (DB::table('fiscal_years')->get(['id', 'company_id']) as $year) {
            $company = DB::table('companies')->find($year->company_id);
            DB::table('fiscal_years')->where('id', $year->id)->update([
                'name' => $company->name,
                'logo' => $company->logo,
                'address' => $company->address,
                'economical_code' => $company->economical_code,
                'national_code' => $company->national_code,
                'postal_code' => $company->postal_code,
                'phone_number' => $company->phone_number,
                'currency' => $company->currency,
                'certificate_path' => $company->certificate_path,
                'private_key_path' => $company->private_key_path,
                'moadian_username' => $company->moadian_username,
                'tax_id' => $company->tax_id,
            ]);
        }

        Schema::table('fiscal_years', function (Blueprint $table) {
            $table->string('name', 50)->nullable(false)->change();
            $table->string('currency', 50)->default('Rial')->nullable(false)->change();
        });

        Schema::table('fiscal_years', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropIndex(['company_id', 'fiscal_year']);
            $table->dropColumn('company_id');
        });

        Schema::rename('companies', 'business_companies');
        Schema::rename('fiscal_years', 'companies');
        Schema::drop('business_companies');
    }
};
