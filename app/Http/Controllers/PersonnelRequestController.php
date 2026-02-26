<?php

namespace App\Http\Controllers;

use App\Enums\PersonnelRequestType;
use App\Models\Employee;
use App\Models\PersonnelRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PersonnelRequestController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->get('tab', 'leaves');

        $types = match ($tab) {
            'missions' => PersonnelRequestType::missionTypes(),
            'work_orders' => PersonnelRequestType::workOrderTypes(),
            'other' => PersonnelRequestType::otherTypes(),
            default => PersonnelRequestType::leaveTypes(),
        };

        $typeValues = array_map(fn ($t) => $t->value, $types);

        $query = PersonnelRequest::with(['employee', 'approvedBy'])
            ->whereIn('request_type', $typeValues)
            ->orderBy('start_date', 'desc');

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $personnelRequests = $query->paginate(15)->withQueryString();

        // Pending counts per tab for badges
        $pendingCounts = [
            'leaves' => PersonnelRequest::whereIn('request_type', array_map(fn ($t) => $t->value, PersonnelRequestType::leaveTypes()))
                ->where('status', 'pending')->count(),
            'missions' => PersonnelRequest::whereIn('request_type', array_map(fn ($t) => $t->value, PersonnelRequestType::missionTypes()))
                ->where('status', 'pending')->count(),
            'work_orders' => PersonnelRequest::whereIn('request_type', array_map(fn ($t) => $t->value, PersonnelRequestType::workOrderTypes()))
                ->where('status', 'pending')->count(),
            'other' => PersonnelRequest::whereIn('request_type', array_map(fn ($t) => $t->value, PersonnelRequestType::otherTypes()))
                ->where('status', 'pending')->count(),
        ];

        $employees = Employee::orderBy('first_name')->get(['id', 'first_name', 'last_name']);

        return view('personnel-requests.index', compact(
            'personnelRequests',
            'pendingCounts',
            'employees',
            'tab'
        ));
    }

    public function create(): View
    {
        $employees = Employee::orderBy('first_name')->get(['id', 'first_name', 'last_name']);
        $requestTypes = PersonnelRequestType::options();

        return view('personnel-requests.create', compact('employees', 'requestTypes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'request_type' => ['required', 'string', 'in:'.implode(',', array_column(PersonnelRequestType::cases(), 'value'))],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'string', 'in:pending,approved,rejected'],
            'approved_by' => ['nullable', 'integer', 'exists:employees,id'],
        ]);

        PersonnelRequest::create(array_merge(
            $validated,
            ['company_id' => getActiveCompany()]
        ));

        return redirect()->route('personnel-requests.index')
            ->with('success', __('Personnel request created successfully.'));
    }

    public function show(PersonnelRequest $personnelRequest): View
    {
        $personnelRequest->load(['employee', 'approvedBy', 'payroll']);

        return view('personnel-requests.show', compact('personnelRequest'));
    }

    public function edit(PersonnelRequest $personnelRequest): View
    {
        $employees = Employee::orderBy('first_name')->get(['id', 'first_name', 'last_name']);
        $requestTypes = PersonnelRequestType::options();

        return view('personnel-requests.edit', compact('personnelRequest', 'employees', 'requestTypes'));
    }

    public function update(Request $request, PersonnelRequest $personnelRequest): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'request_type' => ['required', 'string', 'in:'.implode(',', array_column(PersonnelRequestType::cases(), 'value'))],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'string', 'in:pending,approved,rejected'],
            'approved_by' => ['nullable', 'integer', 'exists:employees,id'],
        ]);

        $personnelRequest->update($validated);

        return redirect()->route('personnel-requests.index')
            ->with('success', __('Personnel request updated successfully.'));
    }

    public function destroy(PersonnelRequest $personnelRequest): RedirectResponse
    {
        $personnelRequest->delete();

        return redirect()->route('personnel-requests.index')
            ->with('success', __('Personnel request deleted successfully.'));
    }
}
