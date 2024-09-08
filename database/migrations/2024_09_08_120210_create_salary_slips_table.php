<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {

        Schema::create('salary_slips', function (Blueprint $table) {
            $table->id(); // آیدی
            $table->string('name'); // نام
            $table->decimal('daily_wage', 8, 2); // مزد روزانه
            $table->decimal('hourly_overtime', 8, 2)->nullable(); // اضافه کار ساعتی
            $table->decimal('holiday_work', 8, 2)->nullable(); // تعطیل کاری
            $table->decimal('friday_work', 8, 2)->nullable(); // جمعه کاری
            $table->decimal('child_allowance', 8, 2)->nullable(); // حق اولاد
            $table->decimal('housing_allowance', 8, 2)->nullable(); // حق مسکن
            $table->decimal('food_allowance', 8, 2)->nullable(); // حق خوار و بار
            $table->decimal('marriage_allowance', 8, 2)->nullable(); // حق تاهل
            $table->foreignId('payroll_pattern_id')->constrained()->onDelete('cascade'); // الگوی محاسبه حقوق
            $table->text('description')->nullable(); // توضیحات
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('salary_slips');
    }
};
