<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERSONNEL_REQUEST_TYPES = [
        'LEAVE_HOURLY',
        'LEAVE_DAILY',
        'SICK_LEAVE',
        'LEAVE_WITHOUT_PAY',
        'LEAVE_WITHOUT_PAY_HOURLY',
        'MISSION_HOURLY',
        'MISSION_DAILY',
        'OVERTIME_ORDER',
        'REMOTE_WORK',
        'OTHER',
    ];

    public function up(): void
    {
        Schema::table('personnel_requests', function (Blueprint $table) {
            $table->enum('request_type', self::PERSONNEL_REQUEST_TYPES)->change();
        });

        Schema::table('monthly_attendances', function (Blueprint $table) {
            $table->unsignedTinyInteger('unpaid_leave_days')->default(0)->after('unpaid_leave');
        });
    }

    public function down(): void
    {
        Schema::table('monthly_attendances', function (Blueprint $table) {
            $table->dropColumn('unpaid_leave_days');
        });

        DB::table('personnel_requests')->where('request_type', 'LEAVE_WITHOUT_PAY_HOURLY')->update(['request_type' => 'LEAVE_WITHOUT_PAY']);

        Schema::table('personnel_requests', function (Blueprint $table) {
            $table->enum('request_type', array_values(array_diff(self::PERSONNEL_REQUEST_TYPES, ['LEAVE_WITHOUT_PAY_HOURLY'])))->change();
        });
    }
};
