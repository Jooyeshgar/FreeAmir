<?php

namespace App\Http\Controllers;

use App\Enums\ThursdayStatus;
use App\Models\WorkShift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkShiftController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->input('search', '');

        $workShifts = WorkShift::orderBy('name')
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->paginate(15);

        return view('work-shifts.index', compact('workShifts', 'search'));
    }

    public function create(): View
    {
        $thursdayStatusOptions = ThursdayStatus::options();

        return view('work-shifts.create', compact('thursdayStatusOptions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'thursday_status' => ['required', 'in:holiday,full_day,half_day'],
            'thursday_exit_time' => ['nullable', 'required_if:thursday_status,half_day', 'date_format:H:i'],
            'float' => ['nullable', 'numeric', 'min:0', 'max:120'],
            'break' => ['nullable', 'integer', 'min:0', 'max:480'],
            'holiday_coefficient' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'overtime_coefficient' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'auto_overtime_coefficient' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'max_auto_overtime' => ['nullable', 'integer', 'min:0', 'max:480'],
            'mission_coefficient' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'undertime_coefficient' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'is_active' => ['boolean'],
            'paid_leave' => ['nullable', 'integer', 'min:0', 'max:15000'],
        ]);

        WorkShift::create(array_merge($validated, [
            'company_id' => getActiveCompany(),
            'is_active' => $request->boolean('is_active', true),
        ]));

        return redirect()->route('attendance.work-shifts.index')
            ->with('success', __('Work shift created successfully.'));
    }

    public function edit(WorkShift $workShift): View
    {
        $thursdayStatusOptions = ThursdayStatus::options();

        return view('work-shifts.edit', compact('workShift', 'thursdayStatusOptions'));
    }

    public function update(Request $request, WorkShift $workShift): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'thursday_status' => ['required', 'in:holiday,full_day,half_day'],
            'thursday_exit_time' => ['nullable', 'required_if:thursday_status,half_day', 'date_format:H:i'],
            'float' => ['nullable', 'numeric', 'min:0', 'max:120'],
            'break' => ['nullable', 'integer', 'min:0', 'max:480'],
            'holiday_coefficient' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'overtime_coefficient' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'auto_overtime_coefficient' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'max_auto_overtime' => ['nullable', 'integer', 'min:0', 'max:480'],
            'mission_coefficient' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'undertime_coefficient' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'is_active' => ['boolean'],
            'paid_leave' => ['nullable', 'integer', 'min:0', 'max:15000'],
        ]);

        if (isset($validated['paid_leave']) && $validated['paid_leave'] != $workShift->paid_leave) {
            $leaveDiff = $validated['paid_leave'] - $workShift->paid_leave;
            foreach ($workShift->employees as $employee) {
                $employee->leave_remain += $leaveDiff;
                $employee->save();
            }
        }

        $workShift->update(array_merge($validated, [
            'is_active' => $request->boolean('is_active'),
        ]));

        return redirect()->route('attendance.work-shifts.index')
            ->with('success', __('Work shift updated successfully.'));
    }

    public function destroy(WorkShift $workShift): RedirectResponse
    {
        $workShift->delete();

        return redirect()->route('attendance.work-shifts.index')
            ->with('success', __('Work shift deleted successfully.'));
    }
}
