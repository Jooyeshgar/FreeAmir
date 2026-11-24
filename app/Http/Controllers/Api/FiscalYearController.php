<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FiscalYear;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FiscalYearController extends Controller
{
    public function index(Request $request, int $company): JsonResponse
    {
        $user = $request->user();

        $currentFiscalYear = FiscalYear::query()->findOrFail($company);

        $query = $currentFiscalYear->company
            ->fiscalYears()
            ->with('company:id,name,currency')
            ->orderByDesc('year');

        $onlyAccessible = $request->boolean('only_accessible', true);

        if ($onlyAccessible) {
            $accessibleFiscalYearIds = $user->fiscalYears()->pluck('fiscal_years.id')->all();

            $query->whereIn('fiscal_years.id', $accessibleFiscalYearIds);
        }

        if ($request->boolean('only_open')) {
            $query->whereNull('closed_at');
        }

        if ($request->boolean('only_closed')) {
            $query->whereNotNull('closed_at');
        }

        $all = $query->get();

        $mapped = $all->map(function (FiscalYear $fiscalYear) use ($company) {
            [$start, $end] = $fiscalYear->range();

            return [
                'id' => $fiscalYear->id,
                'company_id' => $fiscalYear->company_id,
                'year' => (int) $fiscalYear->year,
                'name' => $fiscalYear->company?->name,
                'currency' => $fiscalYear->company?->currency,
                'is_closed' => $fiscalYear->isClosed(),
                'closed_at' => $fiscalYear->closed_at,
                'closed_by' => $fiscalYear->closed_by,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'start_date_jalali' => sprintf('%d/01/01', (int) $fiscalYear->year),
                'end_date_jalali' => sprintf('%d/12/29', (int) $fiscalYear->year),
                'is_current_context' => $fiscalYear->id === $company,
                'pl_document_id' => $fiscalYear->pl_document_id,
                'closing_document_id' => $fiscalYear->closing_document_id,
            ];
        })->values();

        $total = $mapped->count();
        $perPage = min(max((int) $request->input('per_page', 50), 1), 200);
        $page = max((int) $request->input('page', 1), 1);
        $lastPage = max((int) ceil($total / $perPage), 1);

        if ($page > $lastPage) {
            $page = $lastPage;
        }

        $offset = ($page - 1) * $perPage;
        $pageItems = $mapped->slice($offset, $perPage)->values();

        return response()->json([
            'data' => [
                'fiscal_years' => $pageItems,
                'filters' => [
                    'only_accessible' => $onlyAccessible,
                    'only_open' => $request->boolean('only_open'),
                    'only_closed' => $request->boolean('only_closed'),
                ],
                'meta' => [
                    'company_id' => $currentFiscalYear->company_id,
                    'current_fiscal_year_id' => $currentFiscalYear->id,
                    'count' => $total,
                    'open_count' => $mapped->where('is_closed', false)->count(),
                    'closed_count' => $mapped->where('is_closed', true)->count(),
                    'note' => __('Each fiscal year is a separate workspace. Use its id as {fiscal_year} in other API routes.'),
                ],
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => $lastPage,
                    'from' => $total === 0 ? null : $offset + 1,
                    'to' => $total === 0 ? null : min($offset + $perPage, $total),
                ],
            ],
        ]);
    }
}
