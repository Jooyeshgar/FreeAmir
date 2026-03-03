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
        Schema::table('payrolls', function (Blueprint $table) {
            $table->unsignedInteger('monthly_attendance_id')->nullable()->after('decree_id');

            $table->foreign('monthly_attendance_id')
                ->references('id')->on('monthly_attendances')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropForeign(['monthly_attendance_id']);
            $table->dropColumn('monthly_attendance_id');
        });
    }
};
