<?php

namespace App\Services;

use App\Enums\AttendanceImportType;
use App\Models\AttendanceLog;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class AttendanceLogImportService
{
    /**
     * Parse and import the attendance file.
     * $file may be an UploadedFile (from a fresh upload) or a Storage-relative path string (from temp storage).
     *
     * Returns a summary array:
     *   imported  – rows successfully inserted
     *   skipped   – rows skipped (duplicate or ignored log_type)
     *   unknown_devices – device IDs that could not be mapped to an employee
     *
     * @param  string  $duplicateMode  'ignore' – keep existing record unchanged; 'replace' – overwrite entry/exit times.
     */
    public function import(UploadedFile|string $file, AttendanceImportType $type, int $companyId, ?string $dateFrom = null, ?string $dateTo = null, string $duplicateMode = 'ignore'): array
    {
        return match ($type) {
            AttendanceImportType::DeviceTsv => $this->importDeviceTsv($file, $companyId, $dateFrom, $dateTo, $duplicateMode),
        };
    }

    /**
     * Parse the file and return a preview (up to 20 rows) without importing.
     * $file may be an UploadedFile or a Storage-relative path string.
     *
     * Returns:
     *   rows         – up to 20 parsed row arrays for display
     *   total        – total number of valid (non-skipped) rows in the date range
     *   unknown_devices – device IDs not mapped to any employee
     */
    public function preview(UploadedFile|string $file, AttendanceImportType $type, int $companyId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        return match ($type) {
            AttendanceImportType::DeviceTsv => $this->previewDeviceTsv($file, $companyId, $dateFrom, $dateTo),
        };
    }

    /**
     * Read raw file contents from either an UploadedFile or a storage path.
     */
    private function readContents(UploadedFile|string $file): string
    {
        if ($file instanceof UploadedFile) {
            return $file->get();
        }

        return Storage::get($file) ?? '';
    }

    // -----------------------------------------------------------------------
    // Device TSV format
    // Columns (1-indexed, tab-separated):
    //   1 – device_id (employee identification number on the device)
    //   2 – datetime  (Y-m-d H:i:s)
    //   3 – ignored
    //   4 – log_type  (0 = check-in, 1 = check-out; value 2 is fixed/ignored)
    //   5 – ignored
    //   6 – ignored
    // -----------------------------------------------------------------------
    private function importDeviceTsv(UploadedFile|string $file, int $companyId, ?string $dateFrom = null, ?string $dateTo = null, string $duplicateMode = 'ignore'): array
    {
        $lines = array_filter(
            explode("\n", $this->readContents($file)),
            fn ($l) => trim($l) !== ''
        );

        /** @var Collection<string,Employee> $deviceMap */
        $deviceMap = Employee::where('company_id', $companyId)
            ->whereNotNull('device_id')
            ->get()
            ->keyBy('device_id');

        $imported = 0;
        $skipped = 0;
        $unknownDevices = [];

        // First pass: collect all valid rows grouped by [employee_id][log_date]
        // Each group accumulates the latest entry_time and exit_time found in the file.
        // Structure: $pending[$employeeId][$logDate] = ['entry_time' => ..., 'exit_time' => ...]
        $pending = [];

        foreach ($lines as $line) {
            $cols = preg_split('/\t+/', trim($line));

            // Need at least 4 columns
            if (count($cols) < 4) {
                $skipped++;

                continue;
            }

            $deviceId = trim($cols[0]);
            $datetimeStr = trim($cols[1]);
            $rawLogType = (int) trim($cols[3]);

            // col4: 0 = check-in, 1 = check-out, 2 = fixed record (skip)
            if ($rawLogType === 2) {
                $skipped++;

                continue;
            }

            // Resolve employee by device_id
            if (! isset($deviceMap[$deviceId])) {
                if (! in_array($deviceId, $unknownDevices, true)) {
                    $unknownDevices[] = $deviceId;
                }
                $skipped++;

                continue;
            }

            $employee = $deviceMap[$deviceId];

            // Parse datetime
            try {
                $dt = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $datetimeStr);
            } catch (\Exception) {
                $skipped++;

                continue;
            }

            $logDate = $dt->toDateString();
            $logTime = $dt->format('H:i:s');

            // Apply date range filter
            if ($dateFrom !== null && $logDate < $dateFrom) {
                $skipped++;

                continue;
            }
            if ($dateTo !== null && $logDate > $dateTo) {
                $skipped++;

                continue;
            }

            // 0 = check-in, anything else = check-out
            $isEntry = $rawLogType === 0;

            if (! isset($pending[$employee->id][$logDate])) {
                $pending[$employee->id][$logDate] = ['entry_time' => null, 'exit_time' => null];
            }

            if ($isEntry) {
                $pending[$employee->id][$logDate]['entry_time'] = $logTime;
            } else {
                $pending[$employee->id][$logDate]['exit_time'] = $logTime;
            }
        }

        // Second pass: upsert one row per employee per day
        foreach ($pending as $employeeId => $dates) {
            foreach ($dates as $logDate => $times) {
                $existing = AttendanceLog::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where('employee_id', $employeeId)
                    ->where('log_date', $logDate)
                    ->first();

                if ($existing) {
                    if ($duplicateMode === 'replace') {
                        // Overwrite only the fields that are present in the file;
                        // keep existing value for whichever side is missing.
                        $update = [];
                        if ($times['entry_time'] !== null) {
                            $update['entry_time'] = $times['entry_time'];
                        }
                        if ($times['exit_time'] !== null) {
                            $update['exit_time'] = $times['exit_time'];
                        }
                        if (! empty($update)) {
                            $existing->update($update);
                            $imported++;
                        } else {
                            $skipped++;
                        }
                    } else {
                        // 'ignore' – day already has a record, skip entirely
                        $skipped++;
                    }
                } else {
                    AttendanceLog::create([
                        'company_id' => $companyId,
                        'employee_id' => $employeeId,
                        'log_date' => $logDate,
                        'entry_time' => $times['entry_time'],
                        'exit_time' => $times['exit_time'],
                        'is_manual' => false,
                    ]);

                    $imported++;
                }
            }
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'unknown_devices' => $unknownDevices,
        ];
    }

    /**
     * Parse TSV file and return preview data without persisting anything.
     */
    private function previewDeviceTsv(UploadedFile|string $file, int $companyId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $lines = array_filter(
            explode("\n", $this->readContents($file)),
            fn ($l) => trim($l) !== ''
        );

        /** @var Collection<string,Employee> $deviceMap */
        $deviceMap = Employee::where('company_id', $companyId)
            ->whereNotNull('device_id')
            ->get()
            ->keyBy('device_id');

        $rows = [];
        $total = 0;
        $unknownDevices = [];

        foreach ($lines as $line) {
            $cols = preg_split('/\t+/', trim($line));

            if (count($cols) < 4) {
                continue;
            }

            $deviceId = trim($cols[0]);
            $datetimeStr = trim($cols[1]);
            $rawLogType = (int) trim($cols[3]);

            if ($rawLogType === 2) {
                continue;
            }

            // Parse datetime
            try {
                $dt = Carbon::createFromFormat('Y-m-d H:i:s', $datetimeStr);
            } catch (\Exception) {
                continue;
            }

            $logDate = $dt->toDateString();
            $logTime = $dt->format('H:i');

            // Apply date range filter
            if ($dateFrom !== null && $logDate < $dateFrom) {
                continue;
            }
            if ($dateTo !== null && $logDate > $dateTo) {
                continue;
            }

            $logType = $rawLogType === 0 ? 0 : 1;
            $employee = $deviceMap[$deviceId] ?? null;

            if ($employee === null && ! in_array($deviceId, $unknownDevices, true)) {
                $unknownDevices[] = $deviceId;
            }

            $total++;

            if (count($rows) < 20) {
                $rows[] = [
                    'device_id' => $deviceId,
                    'employee_name' => $employee ? ($employee->first_name.' '.$employee->last_name) : null,
                    'log_date' => $logDate,
                    'log_time' => $logTime,
                    'log_type' => $logType === 0 ? __('Check-in') : __('Check-out'),
                ];
            }
        }

        return [
            'rows' => $rows,
            'total' => $total,
            'unknown_devices' => $unknownDevices,
        ];
    }
}
