<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceImportType;
use App\Enums\ThursdayStatus;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\MonthlyAttendance;
use App\Models\PersonnelRequest;
use App\Models\PublicHoliday;
use App\Services\AttendanceLogImportService;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;

class AttendanceLogController extends Controller
{
    public function __construct(private readonly AttendanceService $attendanceService) {}

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
        if ($request->has('log_date')) {
            $request->merge([
                'log_date' => jalaliInputToGregorian($request->input('log_date'), 'log_date'),
            ]);
        }

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

        return redirect()->route('attendance.attendance-logs.index')
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
        if ($request->has('log_date')) {
            $request->merge([
                'log_date' => jalaliInputToGregorian($request->input('log_date'), 'log_date'),
            ]);
        }

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'log_date' => ['required', 'date'],
            'entry_time' => ['nullable', 'date_format:H:i'],
            'exit_time' => ['nullable', 'date_format:H:i', 'after_or_equal:entry_time'],
            'worked' => ['integer', 'min:0'],
            'delay' => ['nullable', 'integer', 'min:0'],
            'early_leave' => ['nullable', 'integer', 'min:0'],
            'overtime' => ['nullable', 'integer', 'min:0'],
            'auto_overtime' => ['nullable', 'integer', 'min:0'],
            'mission' => ['nullable', 'integer', 'min:0'],
            'paid_leave' => ['nullable', 'integer', 'min:0'],
            'unpaid_leave' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        // Editing a log always marks it as manually corrected
        $attendanceLog->update(array_merge(
            $validated,
            ['is_manual' => true]
        ));

        return redirect()->back()->with('success', __('Attendance log updated successfully.'));
    }

    public function destroy(AttendanceLog $attendanceLog): RedirectResponse
    {
        $attendanceLog->delete();

        return redirect()->route('attendance.attendance-logs.index')
            ->with('success', __('Attendance log deleted successfully.'));
    }

    public function show(AttendanceLog $attendanceLog): View
    {
        $attendanceLog->load(['employee.workShift']);

        $employee = $attendanceLog->employee;
        $workShift = $employee?->workShift;

        // Load personnel requests for this employee on this date (affects calculation)
        $personnelRequests = $employee
            ? PersonnelRequest::where('employee_id', $employee->id)
                ->where(function ($q) use ($attendanceLog) {
                    $dateStr = $attendanceLog->log_date->toDateString();
                    $q->whereDate('start_date', '<=', $dateStr)
                        ->whereDate('end_date', '>=', $dateStr);
                })
                ->orderBy('start_date')
                ->get()
            : collect();

        $logDate = $attendanceLog->log_date;
        $isFriday = $logDate->dayOfWeek === Carbon::FRIDAY;
        $isThursday = $logDate->dayOfWeek === Carbon::THURSDAY;
        $isPublicHoliday = PublicHoliday::where('date', $logDate->toDateString())->exists();

        // Determine effective Thursday status and whether it acts as a holiday
        $thursdayStatus = $isThursday ? ($workShift?->thursday_status ?? ThursdayStatus::FULL_DAY) : null;
        $isThursdayHoliday = $isThursday && $thursdayStatus === ThursdayStatus::HOLIDAY;

        // A day is effectively a holiday if it is a public holiday OR a Thursday-holiday
        $isHoliday = $isPublicHoliday || $isThursdayHoliday;

        // Compute what the service WOULD calculate right now (without saving)
        $computed = $this->attendanceService->computeLogColumns($attendanceLog, $workShift, $isFriday, $isHoliday, $isThursday);

        // Effective (real) shift start accounting for float grace window
        $effectiveShiftStart = null;
        if ($workShift) {
            $effectiveShiftStart = Carbon::createFromFormat('H:i:s', $workShift->start_time)
                ->addMinutes((int) ($workShift->float ?? 0))
                ->format('H:i');
        }

        $shiftMinutes = $this->attendanceService->shiftWorkMinutes($workShift);

        // Compute signed diff (minutes) between entry/exit and effective shift boundaries.
        // For Thursday half-day the effective end is thursday_exit_time, not end_time.
        // Positive diffEntry  → arrived after shift start (late).
        // Positive diffExit   → left after shift end (overtime). Negative → early leave.
        $diffEntry = null;
        $diffExit = null;
        if ($workShift && $attendanceLog->entry_time) {
            $shiftStartCarbon = Carbon::createFromFormat('H:i:s', $workShift->start_time);
            $entryCarbon = Carbon::createFromFormat('H:i:s', $attendanceLog->entry_time) ?? Carbon::createFromFormat('H:i', $attendanceLog->entry_time);
            if ($shiftStartCarbon && $entryCarbon) {
                $diffEntry = (int) $shiftStartCarbon->diffInMinutes($entryCarbon, false);
            }
        }
        if ($workShift && $attendanceLog->exit_time) {
            $effectiveEndTime = ($isThursday && $thursdayStatus === ThursdayStatus::HALF_DAY && $workShift->thursday_exit_time)
                ? $workShift->thursday_exit_time
                : $workShift->end_time;
            $shiftEndCarbon = Carbon::createFromFormat('H:i:s', $effectiveEndTime);
            $exitCarbon = Carbon::createFromFormat('H:i:s', $attendanceLog->exit_time) ?? Carbon::createFromFormat('H:i', $attendanceLog->exit_time);
            if ($shiftEndCarbon && $exitCarbon) {
                // Adjust effective end time to mirror the float logic in AttendanceService::shiftDelayEarlyLeave():
                // if the user arrived within the float grace window the shift end slides forward by the same offset.
                if ($workShift->start_time && $attendanceLog->entry_time) {
                    $float = max(0, (int) ($workShift->float ?? 0));
                    $shiftStartForFloat = Carbon::createFromFormat('H:i:s', $workShift->start_time);
                    $entryForFloat = $entryCarbon ?? Carbon::createFromFormat('H:i:s', $attendanceLog->entry_time);
                    if ($shiftStartForFloat && $entryForFloat) {
                        $floatCutoff = $shiftStartForFloat->copy()->addMinutes($float);
                        $lateMinutes = (int) $floatCutoff->diffInMinutes($entryForFloat, false);
                        if ($lateMinutes <= 0) {
                            // Within grace window: end slides by actual entry offset
                            $offset = max(0, (int) $shiftStartForFloat->diffInMinutes($entryForFloat, false));
                            $shiftEndCarbon->addMinutes($offset);
                        } else {
                            // Late beyond float: end slides by the full float amount
                            $shiftEndCarbon->addMinutes($float);
                        }
                    }
                }
                $diffExit = (int) $shiftEndCarbon->diffInMinutes($exitCarbon, false);
            }
        }

        return view('attendance-logs.show', compact(
            'attendanceLog',
            'employee',
            'workShift',
            'isFriday',
            'isThursday',
            'isPublicHoliday',
            'isHoliday',
            'isThursdayHoliday',
            'thursdayStatus',
            'computed',
            'diffEntry',
            'diffExit',
            'effectiveShiftStart',
            'shiftMinutes',
            'personnelRequests'
        ));
    }

    public function recalculate(AttendanceLog $attendanceLog): RedirectResponse
    {
        $this->attendanceService->recalculateLog($attendanceLog);

        return redirect()->back()->with('success', __('Attendance log recalculated successfully.'));
    }

    public function recalculateAll(MonthlyAttendance $monthlyAttendance): RedirectResponse
    {
        foreach ($monthlyAttendance->logs as $log) {
            $this->attendanceService->recalculateLog($log);
        }

        return redirect()->route('attendance.monthly-attendances.show', $monthlyAttendance)->with('success', __('All monthly Attendance logs recalculated successfully.'));
    }

    public function importForm(): View
    {
        $importTypes = AttendanceImportType::options();

        return view('attendance-logs.import', compact('importTypes'));
    }

    public function importPreview(Request $request, AttendanceLogImportService $importService): View
    {
        $request->validate([
            'import_type' => ['required', 'string', new Enum(AttendanceImportType::class)],
            'file' => ['required', 'file', 'max:10240'],
            'date_from' => ['nullable', 'string'],
            'date_to' => ['nullable', 'string'],
            'duplicate_mode' => ['required', 'string', 'in:ignore,replace'],
        ]);

        $type = AttendanceImportType::from($request->input('import_type'));

        $dateFrom = null;
        $dateTo = null;

        if ($request->filled('date_from')) {
            $dateFrom = Carbon::createFromFormat('Y/m/d', jalali_to_gregorian_date($request->input('date_from')))->format('Y-m-d');
        }
        if ($request->filled('date_to')) {
            $dateTo = Carbon::createFromFormat('Y/m/d', jalali_to_gregorian_date($request->input('date_to')))->format('Y-m-d');
        }

        // Store the uploaded file temporarily for the confirm step
        $path = $request->file('file')->store('attendance-import-tmp');

        $duplicateMode = $request->input('duplicate_mode', 'ignore');

        $preview = $importService->preview(
            $request->file('file'),
            $type,
            getActiveCompany(),
            $dateFrom,
            $dateTo
        );

        return view('attendance-logs.import-preview', compact('preview', 'type', 'dateFrom', 'dateTo', 'path', 'duplicateMode'));
    }

    public function importStore(Request $request, AttendanceLogImportService $importService): RedirectResponse
    {
        $request->validate([
            'import_type' => ['required', 'string', new Enum(AttendanceImportType::class)],
            'tmp_path' => ['required', 'string'],
            'date_from_gregorian' => ['nullable', 'string', 'date_format:Y-m-d'],
            'date_to_gregorian' => ['nullable', 'string', 'date_format:Y-m-d'],
            'duplicate_mode' => ['required', 'string', 'in:ignore,replace'],
        ]);

        $type = AttendanceImportType::from($request->input('import_type'));
        $tmpPath = $request->input('tmp_path');

        // Ensure the path is within the expected directory to prevent path traversal
        if (! str_starts_with($tmpPath, 'attendance-import-tmp/') || ! Storage::exists($tmpPath)) {
            return redirect()->route('attendance.attendance-logs.import')
                ->withErrors(['file' => __('Upload session expired. Please upload the file again.')]);
        }

        $dateFrom = $request->input('date_from_gregorian') ?: null;
        $dateTo = $request->input('date_to_gregorian') ?: null;
        $duplicateMode = $request->input('duplicate_mode', 'ignore');

        $result = $importService->import(
            $tmpPath,
            $type,
            getActiveCompany(),
            $dateFrom,
            $dateTo,
            $duplicateMode
        );

        Storage::delete($tmpPath);

        $message = __('Import complete: :imported records imported, :skipped skipped.', [
            'imported' => $result['imported'],
            'skipped' => $result['skipped'],
        ]);

        if (! empty($result['unknown_devices'])) {
            $message .= ' '.__('Unknown device IDs (not mapped to any employee): :ids', [
                'ids' => implode(', ', $result['unknown_devices']),
            ]);
        }

        return redirect()->route('attendance.attendance-logs.index')->with('success', $message);
    }
}
