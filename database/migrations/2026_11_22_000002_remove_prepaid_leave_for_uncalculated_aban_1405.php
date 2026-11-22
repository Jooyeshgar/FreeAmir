<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Under the old prepaid model every employee's leave_remain already contains the paid-leave quota of
 * Aban 1405 (the month that has just ended). With the post-worked accrual model that quota is added again
 * when the Aban monthly attendance is stored, so for employees whose Aban 1405 attendance has not been
 * created yet the prepaid quota is removed once here. Employees whose Aban attendance already exists were
 * accrued by the old logic and are left untouched.
 */
return new class extends Migration
{
    private const LAST_PREPAID_YEAR = 1405;

    private const LAST_PREPAID_MONTH = 8;

    public function up(): void
    {
        $employees = DB::table('employees')
            ->join('work_shifts', 'work_shifts.id', '=', 'employees.work_shift_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('monthly_attendances')
                    ->whereColumn('monthly_attendances.employee_id', 'employees.id')
                    ->where('monthly_attendances.year', self::LAST_PREPAID_YEAR)
                    ->where('monthly_attendances.month', self::LAST_PREPAID_MONTH);
            })
            ->select('employees.id', 'employees.leave_remain', 'work_shifts.paid_leave')
            ->get();

        foreach ($employees as $employee) {
            DB::table('employees')
                ->where('id', $employee->id)
                ->update(['leave_remain' => (int) $employee->leave_remain - (int) $employee->paid_leave]);
        }
    }

    public function down(): void
    {
        // The one-off balance correction is not reversible: it cannot be known which employees were adjusted.
    }
};
