<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->tinyInteger('month')->unsigned();
            $table->string('budget_type', 10);
            $table->decimal('forecast_amount', 18, 2);
            $table->timestamps();

            $table->unique(['company_id', 'month', 'subject_id'], 'uq_monthly_budgets_company_month_subject');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_budgets');
    }
};
