<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('salary_slip_benefit_deduction', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_slip_id')->constrained()->onDelete('cascade'); // ارتباط با حکم حقوق
            $table->foreignId('benefits_deductions_id')->constrained()->onDelete('cascade'); // ارتباط با مزایا و کسورات
            $table->decimal('amount', 10, 2); // مبلغ
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('salary_slip_benefit_deduction');
    }
};
