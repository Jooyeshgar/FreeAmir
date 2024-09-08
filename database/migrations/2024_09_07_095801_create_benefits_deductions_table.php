<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('benefits_deductions', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // نام
            $table->enum('type', ['benefit', 'deduction']); // نوع (مزایا/کسورات)
            $table->enum('calculation', ['fixed', 'hourly', 'manual']); // محاسبه (ثابت/ساعتی/دستی)
            $table->boolean('insurance_included')->default(false); // مشمول بیمه
            $table->boolean('tax_included')->default(false); // مشمول مالیات
            $table->boolean('show_on_payslip')->default(true); // نمایش در فیش
            $table->decimal('amount', 10, 2); // مبلغ
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('benefits_deductions');
    }

};
