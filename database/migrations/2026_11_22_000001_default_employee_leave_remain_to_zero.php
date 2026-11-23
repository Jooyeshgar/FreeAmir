<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->smallInteger('leave_remain')->default(0)->change();
        });

        Schema::table('monthly_attendances', function (Blueprint $table) {
            $table->unsignedSmallInteger('earned_paid_leave')->nullable()->after('paid_leave');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->smallInteger('leave_remain')->default(1200)->change();
        });

        Schema::table('monthly_attendances', function (Blueprint $table) {
            $table->dropColumn('earned_paid_leave');
        });
    }
};
