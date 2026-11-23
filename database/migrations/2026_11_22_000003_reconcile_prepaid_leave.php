<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('prepaid_leave_reconciliation_required')->default(false);
        });

        $this->reconcileBalances();
    }

    public function reconcileBalances(): void
    {
        $employees = DB::table('employees')->whereIn('fiscal_year_id', DB::table('fiscal_years')->select('id')->whereNull('closed_at'));

        DB::transaction(function () use ($employees) {
            foreach ($employees->lockForUpdate()->lazyById() as $employee) {
                $attendance = DB::table('monthly_attendances')
                    ->where('employee_id', $employee->id)
                    ->where('fiscal_year_id', $employee->fiscal_year_id)
                    ->orderByDesc('year')
                    ->orderByDesc('month')
                    ->first(['created_at', 'earned_paid_leave']);

                if ($attendance === null || $attendance->earned_paid_leave !== null) {
                    continue;
                }

                $minutes = null;
                if ($attendance->created_at !== null && $employee->updated_at !== null && $employee->updated_at <= $attendance->created_at) {
                    $minutes = DB::table('work_shifts')
                        ->where('id', $employee->work_shift_id)
                        ->where('fiscal_year_id', $employee->fiscal_year_id)
                        ->where('updated_at', '<=', $attendance->created_at)
                        ->value('paid_leave');
                }

                $employeeQuery = DB::table('employees')->where('id', $employee->id);
                $balance = (int) $employee->leave_remain - (int) $minutes;
                if ($minutes === null || $balance < -32768) {
                    $employeeQuery->update(['prepaid_leave_reconciliation_required' => true]);
                } elseif ($minutes > 0) {
                    $employeeQuery->update(['leave_remain' => $balance]);
                }
            }
        });
    }

    public function down(): void
    {
        // Later attendance may already depend on the corrected balances.
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('prepaid_leave_reconciliation_required');
        });
    }
};
