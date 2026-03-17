<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceImportType;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\PublicHoliday;
use App\Services\AttendanceLogImportService;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
            'worked' => ['nullable', 'integer', 'min:0'],
            'delay' => ['nullable', 'integer', 'min:0'],
            'early_leave' => ['nullable', 'integer', 'min:0'],
            'overtime' => ['nullable', 'integer', 'min:0'],
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

        return redirect()->route('attendance.attendance-logs.index')
            ->with('success', __('Attendance log updated successfully.'));
    }

    public function destroy(AttendanceLog $attendanceLog): RedirectResponse
    {
        $attendanceLog->delete();

        return redirect()->route('attendance.attendance-logs.index')
            ->with('success', __('Attendance log deleted successfully.'));
    }

    public function show(AttendanceLog $attendanceLog, AttendanceService $attendanceService): View
    {
        $attendanceLog->load(['employee.workShift']);

        $employee = $attendanceLog->employee;
        $workShift = $employee?->workShift;

        $logDate = $attendanceLog->log_date;
        $isFriday = $logDate->dayOfWeek === Carbon::FRIDAY;
        $isHoliday = PublicHoliday::where('date', $logDate->toDateString())->exists();

        // Compute what the service WOULD calculate right now (without saving)
        $computed = $attendanceService->computeLogColumns($attendanceLog, $workShift, $isFriday, $isHoliday);

        // Effective (real) shift start accounting for float_before grace window
        $effectiveShiftStart = null;
        if ($workShift) {
            $effectiveShiftStart = Carbon::createFromFormat('H:i:s', $workShift->start_time)
                ->addMinutes((int) ($workShift->float_before ?? 0))
                ->format('H:i');
        }

        $shiftMinutes = $attendanceService->shiftWorkMinutes($workShift);

        return view('attendance-logs.show', compact(
            'attendanceLog',
            'employee',
            'workShift',
            'isFriday',
            'isHoliday',
            'computed',
            'effectiveShiftStart',
            'shiftMinutes'
        ));
    }

    public function recalculate(AttendanceLog $attendanceLog, AttendanceService $attendanceService): RedirectResponse
    {
        $attendanceService->recalculateLog($attendanceLog);

        return redirect()->route('attendance.attendance-logs.show', $attendanceLog)
            ->with('success', __('Attendance log recalculated successfully.'));
    }

    public function importForm(): View
    {
        $importTypes = AttendanceImportType::options();

        return view('attendance-logs.import', compact('importTypes'));
    }

    public function importPreview(Request $request, AttendanceLogImportService $importService): View
    {
        $request->validate([
            'import_type' => ['required', 'string', new \Illuminate\Validation\Rules\Enum(AttendanceImportType::class)],
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
            'import_type' => ['required', 'string', new \Illuminate\Validation\Rules\Enum(AttendanceImportType::class)],
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
