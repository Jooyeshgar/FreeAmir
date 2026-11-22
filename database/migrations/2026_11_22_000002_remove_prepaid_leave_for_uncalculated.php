<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        [$currentYear, $currentMonth] = gregorian_to_jalali((int) now()->format('Y'), (int) now()->format('n'), (int) now()->format('j'));
        $lastPrepaidYear = $currentMonth === 1 ? $currentYear - 1 : $currentYear;
        $lastPrepaidMonth = $currentMonth === 1 ? 12 : $currentMonth - 1;

        $employees = DB::table('employees')
            ->join('work_shifts', 'work_shifts.id', '=', 'employees.work_shift_id')
            ->join('fiscal_years', 'fiscal_years.id', '=', 'employees.fiscal_year_id')
            ->whereNull('fiscal_years.closed_at')
            ->whereNotExists(function ($query) use ($lastPrepaidYear, $lastPrepaidMonth) {
                $query->select(DB::raw(1))
                    ->from('monthly_attendances')
                    ->whereColumn('monthly_attendances.employee_id', 'employees.id')
                    ->where('monthly_attendances.year', $lastPrepaidYear)
                    ->where('monthly_attendances.month', $lastPrepaidMonth);
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
