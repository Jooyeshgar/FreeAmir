<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_shifts', function (Blueprint $table) {
            $table->unsignedSmallInteger('paid_leave')->default(1200);
        });

        Schema::table('monthly_attendances', function (Blueprint $table) {
            $table->smallInteger('paid_leave')->default(1200)->change();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->smallInteger('leave_remain')->default(1200)->change();
        });
    }

    public function down(): void
    {
        Schema::table('work_shifts', function (Blueprint $table) {
            $table->dropColumn('paid_leave');
        });

        Schema::table('monthly_attendances', function (Blueprint $table) {
            $table->unsignedSmallInteger('paid_leave')->default(1200)->change();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->unsignedSmallInteger('leave_remain')->default(1200)->change();
        });
    }
};
