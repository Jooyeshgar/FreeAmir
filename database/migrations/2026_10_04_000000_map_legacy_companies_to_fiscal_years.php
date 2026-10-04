<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COMPANY_FIELDS = [
        'name', 'logo', 'address', 'economical_code', 'national_code',
        'postal_code', 'phone_number', 'currency', 'certificate_path',
        'private_key_path', 'moadian_username', 'tax_id',
    ];

    public function up(): void
    {
        // Compare the stored bytes in PHP; database collations may ignore case or spaces.
        $groups = DB::table('companies')->orderBy('id')->get()->groupBy(
            fn ($row) => bin2hex($row->name)
        );

        foreach ($groups as $rows) {
            $years = [];
            foreach ($rows as $row) {
                if (isset($years[$row->fiscal_year])) {
                    throw new RuntimeException("Duplicate fiscal year {$row->fiscal_year} for company name [{$row->name}] in legacy rows {$years[$row->fiscal_year]} and {$row->id}.");
                }

                $years[$row->fiscal_year] = $row->id;
            }
        }

        Schema::create('company_identities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
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

        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_identity_id')->constrained('company_identities')->restrictOnDelete();
            $table->foreignId('legacy_company_id')->unique()->constrained('companies')->restrictOnDelete();
            $table->unsignedInteger('year');
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->unsignedBigInteger('pl_document_id')->nullable();
            $table->unsignedBigInteger('closing_document_id')->nullable();
            $table->unsignedTinyInteger('closing_recalculation_step')->nullable();
            $table->unique(['company_identity_id', 'year']);
        });

        Schema::create('fiscal_year_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unique(['fiscal_year_id', 'user_id']);
        });

        foreach ($groups as $rows) {
            $identity = [];
            foreach (self::COMPANY_FIELDS as $field) {
                $identity[$field] = $rows->sortByDesc('id')->first(
                    fn ($row) => $row->{$field} !== null
                )?->{$field};
            }

            $identityId = DB::table('company_identities')->insertGetId($identity);

            foreach ($rows as $row) {
                $fiscalYearId = DB::table('fiscal_years')->insertGetId([
                    'company_identity_id' => $identityId,
                    'legacy_company_id' => $row->id,
                    'year' => $row->fiscal_year,
                    'closed_at' => $row->closed_at,
                    'closed_by' => $row->closed_by,
                    'pl_document_id' => $row->pl_document_id,
                    'closing_document_id' => $row->closing_document_id,
                    'closing_recalculation_step' => $row->closing_recalculation_step,
                ]);

                $userIds = DB::table('company_user')->where('company_id', $row->id)->distinct()->pluck('user_id');
                foreach ($userIds as $userId) {
                    DB::table('fiscal_year_user')->insert([
                        'fiscal_year_id' => $fiscalYearId,
                        'user_id' => $userId,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_year_user');
        Schema::dropIfExists('fiscal_years');
        Schema::dropIfExists('company_identities');
    }
};
