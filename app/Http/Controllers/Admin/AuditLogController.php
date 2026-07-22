<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'method' => ['nullable', Rule::in(['POST', 'PUT', 'PATCH', 'DELETE'])],
            'from' => ['nullable', 'string', 'max:10'],
            'to' => ['nullable', 'string', 'max:10'],
            'sort' => ['nullable', Rule::in(['created_at', 'action', 'method'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $sort = $filters['sort'] ?? 'created_at';
        $direction = $filters['direction'] ?? 'desc';
        $companyId = $filters['company_id'] ?? $request->session()->get(config('admin.company_filter_session_key', 'admin.company_filter_id'));
        $from = filled($filters['from'] ?? null) ? jalaliInputToGregorian($filters['from'], 'from') : null;
        $to = filled($filters['to'] ?? null) ? jalaliInputToGregorian($filters['to'], 'to') : null;

        if ($from && $to && $to < $from) {
            throw ValidationException::withMessages([
                'to' => __('validation.after_or_equal', [
                    'attribute' => __('To'),
                    'date' => __('From'),
                ]),
            ]);
        }

        $auditLogs = AuditLog::query()->with(['actor:id,name,email', 'company:id,name'])->when($filters['search'] ?? null, fn ($query, string $search) => $query
            ->where(fn ($query) => $query->where('action', 'like', "%{$search}%")->orWhere('url', 'like', "%{$search}%")->orWhere('ip_address', 'like', "%{$search}%")))
            ->when($filters['user_id'] ?? null, fn ($query, int $userId) => $query->where('user_id', $userId))
            ->when($companyId, fn ($query, int $selectedCompanyId) => $query->where('company_id', $selectedCompanyId))
            ->when($filters['method'] ?? null, fn ($query, string $method) => $query->where('method', $method))
            ->when($from, fn ($query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($to, fn ($query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->orderBy($sort, $direction)
            ->paginate(25)
            ->withQueryString();

        return view('admin.audit-logs.index', [
            'auditLogs' => $auditLogs,
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'companies' => Company::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(AuditLog $auditLog)
    {
        $auditLog->load(['actor:id,name,email', 'company:id,name']);

        return view('admin.audit-logs.show', compact('auditLog'));
    }
}
