<?php

namespace App\Http\Controllers;

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
        return view('work-shifts.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'crosses_midnight' => ['boolean'],
            'float_before' => ['nullable', 'integer', 'min:0', 'max:120'],
            'float_after' => ['nullable', 'integer', 'min:0', 'max:120'],
            'break' => ['nullable', 'integer', 'min:0', 'max:480'],
            'is_active' => ['boolean'],
        ]);

        WorkShift::create(array_merge($validated, [
            'company_id' => getActiveCompany(),
            'crosses_midnight' => $request->boolean('crosses_midnight'),
            'is_active' => $request->boolean('is_active', true),
        ]));

        return redirect()->route('work-shifts.index')
            ->with('success', __('Work shift created successfully.'));
    }

    public function edit(WorkShift $workShift): View
    {
        return view('work-shifts.edit', compact('workShift'));
    }

    public function update(Request $request, WorkShift $workShift): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'crosses_midnight' => ['boolean'],
            'float_before' => ['nullable', 'integer', 'min:0', 'max:120'],
            'float_after' => ['nullable', 'integer', 'min:0', 'max:120'],
            'break' => ['nullable', 'integer', 'min:0', 'max:480'],
            'is_active' => ['boolean'],
        ]);

        $workShift->update(array_merge($validated, [
            'crosses_midnight' => $request->boolean('crosses_midnight'),
            'is_active' => $request->boolean('is_active'),
        ]));

        return redirect()->route('work-shifts.index')
            ->with('success', __('Work shift updated successfully.'));
    }

    public function destroy(WorkShift $workShift): RedirectResponse
    {
        $workShift->delete();

        return redirect()->route('work-shifts.index')
            ->with('success', __('Work shift deleted successfully.'));
    }
}
