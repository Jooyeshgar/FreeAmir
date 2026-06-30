<?php

namespace App\Http\Controllers\Api;

use App\Filters\ApiAttendanceLogFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreAttendanceLogsRequest;
use App\Models\AttendanceLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AttendanceLogController extends Controller
{
    public function index(Request $request, ApiAttendanceLogFilter $filter): JsonResponse
    {
        $request->validate([
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('employees', 'id')->where('company_id', getActiveCompany()),
            ],
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        $logs = AttendanceLog::filter($filter)
            ->orderBy('log_date')
            ->get();

        return response()->json(['data' => $logs]);
    }

    public function store(StoreAttendanceLogsRequest $request): JsonResponse
    {
        $logs = DB::transaction(fn () => collect($request->validated('logs'))
            ->map(fn (array $log) => AttendanceLog::create(array_merge($log, [
                'company_id' => getActiveCompany(),
                'is_manual' => $log['is_manual'] ?? false,
            ]))));

        return response()->json([
            'data' => $logs,
            'meta' => ['count' => $logs->count()],
        ], 201);
    }
}
