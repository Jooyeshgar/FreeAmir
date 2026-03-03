<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_attendances', function (Blueprint $table) {
            $table->date('start_date')->nullable()->after('month');
            $table->tinyInteger('duration')->unsigned()->nullable()->after('start_date')
                ->comment('Number of calendar days in the Jalali month (28–31)');
        });
    }

    public function down(): void
    {
        Schema::table('monthly_attendances', function (Blueprint $table) {
            $table->dropColumn(['start_date', 'duration']);
        });
    }
};
