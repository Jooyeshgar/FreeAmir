<?php

namespace App\Http\Controllers;

use App\Enums\PersonnelRequestType;
use App\Models\AttendanceLog;
use App\Models\MonthlyAttendance;
use App\Models\Payroll;
use App\Models\PersonnelRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeePortalController extends Controller
{
    /**
     * Return the employee record for the currently authenticated user.
     */
    private function currentEmployee()
    {
        return auth()->user()->employee;
    }

    /**
     * Employee self-service dashboard.
     */
    public function dashboard(): View
    {
        $employee = $this->currentEmployee();

        $recentLogs = AttendanceLog::where('employee_id', $employee->id)
            ->orderBy('log_date', 'desc')
            ->limit(5)
            ->get();

        $pendingRequests = PersonnelRequest::where('employee_id', $employee->id)
            ->where('status', 'pending')
            ->count();

        return view('employee-portal.dashboard', compact('employee', 'recentLogs', 'pendingRequests'));
    }

    /**
     * List attendance logs for the current employee.
     */
    public function attendanceLogs(Request $request): View
    {
        $employee = $this->currentEmployee();

        $query = AttendanceLog::where('employee_id', $employee->id)
            ->orderBy('log_date', 'desc');

        if ($request->filled('date_from')) {
            $query->whereDate('log_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('log_date', '<=', $request->date_to);
        }

        $attendanceLogs = $query->paginate(15)->withQueryString();

        return view('employee-portal.attendance-logs', compact('attendanceLogs', 'employee'));
    }

    /**
     * List monthly attendances for the current employee.
     */
    public function monthlyAttendances(Request $request): View
    {
        $employee = $this->currentEmployee();

        $query = MonthlyAttendance::where('employee_id', $employee->id)
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc');

        if ($request->filled('year')) {
            $query->where('year', $request->integer('year'));
        }

        if ($request->filled('month')) {
            $query->where('month', $request->integer('month'));
        }

        $monthlyAttendances = $query->paginate(15)->withQueryString();

        return view('employee-portal.monthly-attendances', compact('monthlyAttendances', 'employee'));
    }

    /**
     * Show a single monthly attendance detail for the current employee.
     */
    public function monthlyAttendanceShow(MonthlyAttendance $monthlyAttendance): View
    {
        $employee = $this->currentEmployee();

        abort_if($monthlyAttendance->employee_id !== $employee->id, 403);

        $monthlyAttendance->load(['logs' => fn ($q) => $q->orderBy('log_date')]);

        return view('employee-portal.monthly-attendance-show', compact('monthlyAttendance', 'employee'));
    }

    /**
     * List payrolls for the current employee.
     */
    public function payrolls(Request $request): View
    {
        $employee = $this->currentEmployee();

        $query = Payroll::where('employee_id', $employee->id)
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc');

        if ($request->filled('year')) {
            $query->where('year', $request->integer('year'));
        }

        if ($request->filled('month')) {
            $query->where('month', $request->integer('month'));
        }

        $payrolls = $query->paginate(15)->withQueryString();

        return view('employee-portal.payrolls', compact('payrolls', 'employee'));
    }

    /**
     * List personnel requests for the current employee.
     */
    public function personnelRequests(Request $request): View
    {
        $employee = $this->currentEmployee();

        $tab = $request->get('tab', 'leaves');

        $types = match ($tab) {
            'missions' => PersonnelRequestType::missionTypes(),
            'work_orders' => PersonnelRequestType::workOrderTypes(),
            'other' => PersonnelRequestType::otherTypes(),
            default => PersonnelRequestType::leaveTypes(),
        };

        $typeValues = array_map(fn ($t) => $t->value, $types);

        $personnelRequests = PersonnelRequest::where('employee_id', $employee->id)
            ->whereIn('request_type', $typeValues)
            ->orderBy('start_date', 'desc')
            ->paginate(15)
            ->withQueryString();

        $pendingCounts = [
            'leaves' => PersonnelRequest::where('employee_id', $employee->id)
                ->whereIn('request_type', array_map(fn ($t) => $t->value, PersonnelRequestType::leaveTypes()))
                ->where('status', 'pending')->count(),
            'missions' => PersonnelRequest::where('employee_id', $employee->id)
                ->whereIn('request_type', array_map(fn ($t) => $t->value, PersonnelRequestType::missionTypes()))
                ->where('status', 'pending')->count(),
            'work_orders' => PersonnelRequest::where('employee_id', $employee->id)
                ->whereIn('request_type', array_map(fn ($t) => $t->value, PersonnelRequestType::workOrderTypes()))
                ->where('status', 'pending')->count(),
            'other' => PersonnelRequest::where('employee_id', $employee->id)
                ->whereIn('request_type', array_map(fn ($t) => $t->value, PersonnelRequestType::otherTypes()))
                ->where('status', 'pending')->count(),
        ];

        return view('employee-portal.personnel-requests.index', compact(
            'personnelRequests',
            'pendingCounts',
            'employee',
            'tab'
        ));
    }

    /**
     * Show form to create a new personnel request.
     */
    public function createPersonnelRequest(): View
    {
        $requestTypes = PersonnelRequestType::options();

        return view('employee-portal.personnel-requests.create', compact('requestTypes'));
    }

    /**
     * Store a new personnel request submitted by the current employee.
     */
    public function storePersonnelRequest(Request $request): RedirectResponse
    {
        $employee = $this->currentEmployee();

        $validated = $request->validate([
            'request_type' => ['required', 'string', 'in:' . implode(',', array_column(PersonnelRequestType::cases(), 'value'))],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        PersonnelRequest::create(array_merge($validated, [
            'employee_id' => $employee->id,
            'company_id' => getActiveCompany(),
            'status' => 'pending',
        ]));

        return redirect()->route('employee-portal.personnel-requests.index')
            ->with('success', __('Your request has been submitted successfully.'));
    }
}
