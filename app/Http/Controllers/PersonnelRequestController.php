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

    public function create(Request $request): View
    {
        $tab = $request->get('tab', 'leaves');

        $cases = match ($tab) {
            'missions' => PersonnelRequestType::missionTypes(),
            'work_orders' => PersonnelRequestType::workOrderTypes(),
            'other' => PersonnelRequestType::otherTypes(),
            default => PersonnelRequestType::leaveTypes(),
        };

        $requestTypes = array_column(
            array_map(fn ($case) => ['value' => $case->value, 'label' => $case->label()], $cases),
            'label',
            'value'
        );

        $title = match ($tab) {
            'missions' => __('New Mission Request'),
            'work_orders' => __('New Work Order Request'),
            'other' => __('New Other Request'),
            default => __('New Leave Request'),
        };

        $employees = Employee::orderBy('first_name')->get(['id', 'first_name', 'last_name']);

        return view('personnel-requests.create', compact('employees', 'requestTypes', 'tab', 'title'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'request_type' => ['required', 'string', 'in:'.implode(',', array_column(PersonnelRequestType::cases(), 'value'))],
            'request_date' => ['required', 'string'],
            'start_time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'end_time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'string', 'in:pending,approved,rejected'],
        ]);

        $gregorianDate = str_replace('/', '-', convertToGregorian($request->request_date));

        $startDatetime = $gregorianDate.' '.$request->start_time;
        $endDatetime = $gregorianDate.' '.$request->end_time;

        abort_if(
            strtotime($endDatetime) < strtotime($startDatetime),
            422,
            __('End time must be after or equal to start time.')
        );

        PersonnelRequest::create([
            'employee_id' => $request->employee_id,
            'company_id' => getActiveCompany(),
            'request_type' => $request->request_type,
            'start_date' => $startDatetime,
            'end_date' => $endDatetime,
            'reason' => $request->reason,
            'status' => $request->status,
        ]);

        $tab = $request->get('tab', 'leaves');

        return redirect()->route('hr.personnel-requests.index', ['tab' => $tab])
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
            'reason' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'string', 'in:pending,approved,rejected'],
        ]);

        $personnelRequest->update($validated);

        return redirect()->route('hr.personnel-requests.index')
            ->with('success', __('Personnel request updated successfully.'));
    }

    public function destroy(PersonnelRequest $personnelRequest): RedirectResponse
    {
        $personnelRequest->delete();

        return redirect()->route('hr.personnel-requests.index')
            ->with('success', __('Personnel request deleted successfully.'));
    }

    public function approve(PersonnelRequest $personnelRequest): RedirectResponse
    {
        $personnelRequest->update([
            'status' => 'approved',
            'approved_by' => auth()->user()->id,
        ]);

        return redirect()->back()
            ->with('success', __('Personnel request approved.'));
    }

    public function reject(PersonnelRequest $personnelRequest): RedirectResponse
    {
        $personnelRequest->update([
            'status' => 'rejected',
            'approved_by' => auth()->user()->id,
        ]);

        return redirect()->back()
            ->with('success', __('Personnel request rejected.'));
    }
}
