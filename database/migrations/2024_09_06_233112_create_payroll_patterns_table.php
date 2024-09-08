<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('payroll_patterns', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // نام
            $table->decimal('daily_wage', 10, 2); // مزد روزانه
            $table->decimal('overtime_hourly', 10, 2)->nullable(); // اضافه کار ساعتی
            $table->decimal('holiday_work', 10, 2)->nullable(); // تعطیل کاری
            $table->decimal('friday_work', 10, 2)->nullable(); // جمعه کاری
            $table->decimal('child_allowance', 10, 2)->nullable(); // حق اولاد
            $table->decimal('housing_allowance', 10, 2)->nullable(); // حق مسکن
            $table->decimal('grocery_allowance', 10, 2)->nullable(); // حق خواروبار
            $table->decimal('marriage_allowance', 10, 2)->nullable(); // حق تاهل
            $table->decimal('insurance_percentage', 5, 2)->nullable(); // درصد بیمه
            $table->decimal('unemployment_insurance', 5, 2)->nullable(); // بیمه بیکاری
            $table->decimal('employer_share', 5, 2)->nullable(); // سهم کارفرما
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('payroll_patterns');
    }

};
