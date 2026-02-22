<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('work_sites', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 200);
            $table->string('code', 20)->unique();
            $table->text('address')->nullable();
            $table->string('phone', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('contract_frameworks', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 200);
            $table->string('code', 20)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('work_site_contracts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('work_site_id');
            $table->unsignedInteger('contract_framework_id');
            $table->timestamp('created_at')->nullable()->useCurrent();

            $table->unique(['work_site_id', 'contract_framework_id'], 'uq_work_site_contract');

            $table->foreign('work_site_id')
                ->references('id')->on('work_sites')
                ->cascadeOnDelete();

            $table->foreign('contract_framework_id')
                ->references('id')->on('contract_frameworks')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_site_contracts');
        Schema::dropIfExists('contract_frameworks');
        Schema::dropIfExists('work_sites');
    }
};
