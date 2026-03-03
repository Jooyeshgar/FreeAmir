<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = AttendanceLog::with('employee')->orderBy('log_date', 'desc');

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('log_date', '>=', Carbon::createFromFormat('Y/m/d', jalali_to_gregorian_date($request->date_from))->format('Y-m-d'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('log_date', '<=', Carbon::createFromFormat('Y/m/d', jalali_to_gregorian_date($request->date_to))->format('Y-m-d'));
        }

        if ($request->filled('is_manual')) {
            $query->where('is_manual', (bool) $request->is_manual);
        }

        $attendanceLogs = $query->paginate(15);
        $employees = Employee::orderBy('first_name')->get();

        return view('attendance-logs.index', compact('attendanceLogs', 'employees'));
    }

    public function create(): View
    {
        $employees = Employee::orderBy('first_name')->get();

        return view('attendance-logs.create', compact('employees'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'log_date' => Carbon::createFromFormat('Y/m/d', jalali_to_gregorian_date($request->input('log_date')))->format('Y-m-d'),
        ]);

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'log_date' => ['required', 'date'],
            'entry_time' => ['nullable', 'date_format:H:i'],
            'exit_time' => ['nullable', 'date_format:H:i', 'after_or_equal:entry_time'],
            'is_manual' => ['boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        AttendanceLog::create(array_merge(
            $validated,
            [
                'company_id' => getActiveCompany(),
                'is_manual' => $request->boolean('is_manual'),
            ]
        ));

        return redirect()->route('attendance-logs.index')
            ->with('success', __('Attendance log created successfully.'));
    }

    public function edit(AttendanceLog $attendanceLog): View
    {
        $employees = Employee::orderBy('first_name')->get();

        $attendanceLog->entry_time = $attendanceLog->entry_time
            ? substr($attendanceLog->entry_time, 0, 5)
            : null;
        $attendanceLog->exit_time = $attendanceLog->exit_time
            ? substr($attendanceLog->exit_time, 0, 5)
            : null;

        return view('attendance-logs.edit', compact('attendanceLog', 'employees'));
    }

    public function update(Request $request, AttendanceLog $attendanceLog): RedirectResponse
    {
        $request->merge([
            'log_date' => Carbon::createFromFormat('Y/m/d', jalali_to_gregorian_date($request->input('log_date')))->format('Y-m-d'),
        ]);

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'log_date' => ['required', 'date'],
            'entry_time' => ['nullable', 'date_format:H:i'],
            'exit_time' => ['nullable', 'date_format:H:i', 'after_or_equal:entry_time'],
            'is_manual' => ['boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $attendanceLog->update(array_merge(
            $validated,
            ['is_manual' => $request->boolean('is_manual')]
        ));

        return redirect()->route('attendance-logs.index')
            ->with('success', __('Attendance log updated successfully.'));
    }

    public function destroy(AttendanceLog $attendanceLog): RedirectResponse
    {
        $attendanceLog->delete();

        return redirect()->route('attendance-logs.index')
            ->with('success', __('Attendance log deleted successfully.'));
    }
}
