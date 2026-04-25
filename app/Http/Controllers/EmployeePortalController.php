<?php

namespace App\Http\Controllers;

use App\Enums\PersonnelRequestType;
use App\Enums\ThursdayStatus;
use App\Models\AttendanceLog;
use App\Models\MonthlyAttendance;
use App\Models\Payroll;
use App\Models\PersonnelRequest;
use App\Models\PublicHoliday;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EmployeePortalController extends Controller
{
    private const FLEXIBLE_TIME_REGEX = '/^(\d{1,2}):(\d{1,2})$/';

    /**
     * Return the employee record for the currently authenticated user.
     */
    private function currentEmployee()
    {
        return auth()->user()->employee;
    }

    /**
     * Validate personnel request payload and normalize flexible H:i inputs.
     */
    private function validatePersonnelRequestInput(Request $request): array
    {
        $validated = $request->validate([
            'request_type' => ['required', 'string', 'in:'.implode(',', array_column(PersonnelRequestType::cases(), 'value'))],
            'request_date' => ['required', 'string'],
            'start_time' => ['required', 'regex:'.self::FLEXIBLE_TIME_REGEX],
            'end_time' => ['required', 'regex:'.self::FLEXIBLE_TIME_REGEX],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $validated['start_time'] = $this->normalizeFlexibleTime($validated['start_time'], 'start_time');
        $validated['end_time'] = $this->normalizeFlexibleTime($validated['end_time'], 'end_time');

        return $validated;
    }

    /**
     * Normalize flexible time inputs like 7:3 to 07:03.
     */
    private function normalizeFlexibleTime(string $time, string $field): string
    {
        if (! preg_match(self::FLEXIBLE_TIME_REGEX, $time, $matches)) {
            throw ValidationException::withMessages([
                $field => __('The :attribute format is invalid.', ['attribute' => $field]),
            ]);
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];

        if ($hour > 23 || $minute > 59) {
            throw ValidationException::withMessages([
                $field => __('The :attribute format is invalid.', ['attribute' => $field]),
            ]);
        }

        return sprintf('%02d:%02d', $hour, $minute);
    }

    public function changeUserInformation(): View
    {
        $employee = $this->currentEmployee();

        return view('employee-portal.change-user-information', compact('employee'));
    }

    public function updateUserInformation(Request $request): RedirectResponse
    {
        $employee = $this->currentEmployee();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$employee->user->id],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $employee->update([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
        ]);

        $employee->user->update([
            'name' => $validated['first_name'].' '.$validated['last_name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
        ]);

        return redirect()->route('employee-portal.dashboard')->with('success', __('Your information has been updated successfully.'));
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

        $requests = PersonnelRequest::where('employee_id', $employee->id)->count();

        $lastMonthlyAttendance = MonthlyAttendance::where('employee_id', $employee->id)
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->first();

        return view('employee-portal.dashboard', compact('employee', 'recentLogs', 'requests', 'lastMonthlyAttendance'));
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
            $query->whereDate('log_date', '>=', Carbon::createFromFormat('Y/m/d', jalali_to_gregorian_date($request->date_from))->format('Y-m-d'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('log_date', '<=', Carbon::createFromFormat('Y/m/d', jalali_to_gregorian_date($request->date_to))->format('Y-m-d'));
        }

        if ($request->filled('is_manual')) {
            $query->where('is_manual', (bool) $request->is_manual);
        }

        $attendanceLogs = $query->paginate(30)->withQueryString();

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

        if ($monthlyAttendance->employee_id !== $employee->id) {
            throw ValidationException::withMessages([
                'employee_id' => __('Invalid employee_id.'),
            ]);
        }

        $monthlyAttendance->load(['logs' => fn ($q) => $q->orderBy('log_date')]);

        $start = $monthlyAttendance->start_date->copy();
        $end = $start->copy()->addDays($monthlyAttendance->duration - 1);
        $holidayDates = PublicHoliday::withoutGlobalScopes()
            ->where('company_id', $monthlyAttendance->company_id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->pluck('date')
            ->map(fn ($d) => $d instanceof Carbon ? $d->toDateString() : (string) $d)
            ->flip()
            ->toArray();

        $workShift = $monthlyAttendance->employee?->workShift;
        $thursdayIsHoliday = $workShift?->thursday_status === ThursdayStatus::HOLIDAY;

        $logsByDate = $monthlyAttendance->logs->keyBy(fn ($log) => $log->log_date->toDateString());
        $allDays = collect();
        for ($i = 0; $i < $monthlyAttendance->duration; $i++) {
            $date = $start->copy()->addDays($i);
            $dateKey = $date->toDateString();
            $allDays->push(
                $logsByDate->has($dateKey)
                    ? $logsByDate->get($dateKey)
                    : (object) [
                        'log_date' => $date,
                        '_placeholder' => true,
                        '_is_friday' => $date->dayOfWeek === Carbon::FRIDAY,
                        '_is_holiday' => isset($holidayDates[$dateKey])
                            || ($thursdayIsHoliday && $date->dayOfWeek === Carbon::THURSDAY),
                    ]
            );
        }

        return view('monthly-attendances.show', compact('monthlyAttendance', 'allDays'))
            ->with('isAdminView', false)
            ->with('backRoute', route('employee-portal.monthly-attendances'));
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
     * Show payroll detail for the current employee.
     */
    public function payrollShow(Payroll $payroll): View
    {
        $employee = $this->currentEmployee();

        if ($payroll->employee_id !== $employee->id) {
            throw ValidationException::withMessages([
                'employee_id' => __('Invalid employee_id.'),
            ]);
        }

        $payroll->load(['employee', 'decree.benefits.element', 'monthlyAttendance', 'items.element']);

        return view('payrolls.show', compact('payroll'))
            ->with('isEmployeeView', true);
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
     * Accepts an optional ?tab= query param to pre-filter available request types.
     */
    public function createPersonnelRequest(Request $request): View
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

        return view('employee-portal.personnel-requests.create', compact('requestTypes', 'tab', 'title'));
    }

    /**
     * Store a new personnel request submitted by the current employee.
     */
    public function storePersonnelRequest(Request $request): RedirectResponse
    {
        $employee = $this->currentEmployee();

        if (isset($request['request_type']) && in_array($request['request_type'], ['LEAVE_DAILY', 'MISSION_DAILY'])) {
            $request['start_time'] = Carbon::createFromTimeString($employee->workShift->start_time)->format('H:i');
            $request['end_time'] = Carbon::createFromTimeString($employee->workShift->end_time)->format('H:i');
        }

        $validated = $this->validatePersonnelRequestInput($request);

        $gregorianDate = convertToGregorian($validated['request_date']);
        $gregorianDate = str_replace('/', '-', $gregorianDate);

        $startDatetime = $gregorianDate.' '.$validated['start_time'];
        $endDatetime = $gregorianDate.' '.$validated['end_time'];

        if (strtotime($endDatetime) < strtotime($startDatetime)) {
            throw ValidationException::withMessages([
                'end_time' => __('End time must be after or equal to start time.'),
            ]);
        }

        PersonnelRequest::create([
            'employee_id' => $employee->id,
            'company_id' => getActiveCompany(),
            'request_type' => $validated['request_type'],
            'start_date' => $startDatetime,
            'end_date' => $endDatetime,
            'reason' => $validated['reason'] ?? null,
            'status' => 'pending',
        ]);

        $tab = $request->get('tab', 'leaves');

        if ($request->input('submit_action') === 'create_new') {
            return redirect()->route('employee-portal.personnel-requests.create', ['tab' => $tab])->with('success', __('Personnel request created successfully.'));
        }

        return redirect()->route('employee-portal.personnel-requests.index', ['tab' => $tab])
            ->with('success', __('Your request has been submitted successfully.'));
    }

    /**
     * Show form to edit an existing personnel request.
     * Accepts an optional ?tab= query param to pre-filter available request types.
     */
    public function editPersonnelRequest(Request $request, PersonnelRequest $personnelRequest): View
    {
        if ($personnelRequest->status !== 'pending') {
            abort(403, __('Only pending requests can be edited.'));
        }

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
            'missions' => __('Edit Mission Request'),
            'work_orders' => __('Edit Work Order Request'),
            'other' => __('Edit Other Request'),
            default => __('Edit Leave Request'),
        };

        return view('employee-portal.personnel-requests.edit', compact('personnelRequest', 'requestTypes', 'tab', 'title'));
    }

    /**
     * Update an existing personnel request submitted by the current employee.
     */
    public function updatePersonnelRequest(Request $request, PersonnelRequest $personnelRequest): RedirectResponse
    {
        if ($personnelRequest->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => __('Only pending requests can be edited.'),
            ]);
        }

        $employee = $this->currentEmployee();

        if ($personnelRequest->employee_id !== $employee->id) {
            throw ValidationException::withMessages([
                'employee_id' => __('Invalid employee_id.'),
            ]);
        }

        $validated = $this->validatePersonnelRequestInput($request);

        $gregorianDate = convertToGregorian($validated['request_date']);
        $gregorianDate = str_replace('/', '-', $gregorianDate);

        $startDatetime = $gregorianDate.' '.$validated['start_time'];
        $endDatetime = $gregorianDate.' '.$validated['end_time'];

        if (strtotime($endDatetime) < strtotime($startDatetime)) {
            throw ValidationException::withMessages([
                'end_time' => __('End time must be after or equal to start time.'),
            ]);
        }

        $personnelRequest->update([
            'request_type' => $validated['request_type'],
            'start_date' => $startDatetime,
            'end_date' => $endDatetime,
            'reason' => $validated['reason'] ?? null,
        ]);

        $tab = $request->get('tab', 'leaves');

        return redirect()->route('employee-portal.personnel-requests.index', ['tab' => $tab])
            ->with('success', __('Your request has been updated successfully.'));
    }

    public function destroyPersonnelRequest(Request $request, PersonnelRequest $personnelRequest): RedirectResponse
    {
        if ($personnelRequest->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => __('Only pending requests can be deleted.'),
            ]);
        }

        $employee = $this->currentEmployee();

        if ($personnelRequest->employee_id !== $employee->id) {
            throw ValidationException::withMessages([
                'employee_id' => __('Invalid employee_id.'),
            ]);
        }

        $personnelRequest->delete();

        $tab = $request->get('tab', 'leaves');

        return redirect()->route('employee-portal.personnel-requests.index', ['tab' => $tab])
            ->with('success', __('Your request has been deleted successfully.'));
    }
}
