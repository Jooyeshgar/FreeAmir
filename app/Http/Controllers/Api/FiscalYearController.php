<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FiscalYear;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FiscalYearController extends Controller
{
    public function index(Request $request, int $company): JsonResponse
    {
        $user = $request->user();

        $currentFiscalYear = FiscalYear::query()
            ->where('legacy_company_id', $company)
            ->firstOrFail();

        $query = $currentFiscalYear->companyIdentity
            ->fiscalYears()
            ->with('legacyCompany:id,name,currency')
            ->orderByDesc('year');

        $onlyAccessible = $request->boolean('only_accessible', true);

        if ($onlyAccessible) {
            $accessibleLegacyIds = $user->fiscalYears()
                ->pluck('legacy_company_id')
                ->all();

            $query->whereIn('legacy_company_id', $accessibleLegacyIds);
        }

        if ($request->boolean('only_open')) {
            $query->whereNull('closed_at');
        }

        if ($request->boolean('only_closed')) {
            $query->whereNotNull('closed_at');
        }

        $all = $query->get();

        $mapped = $all->map(function (FiscalYear $fy) use ($company) {
            $year = (int) $fy->year;
            $start = Carbon::parse(jalali_to_gregorian($year, 1, 1, '/'))->startOfDay();
            $end = Carbon::parse(jalali_to_gregorian($year + 1, 1, 1, '/'))->subDay()->endOfDay();
            $legacyCompany = $fy->legacyCompany;

            return [
                'id' => $fy->id,
                'company_id' => $fy->legacy_company_id,
                'year' => $year,
                'name' => $legacyCompany?->name,
                'currency' => $legacyCompany?->currency,
                'is_closed' => $fy->closed_at !== null,
                'closed_at' => $fy->closed_at,
                'closed_by' => $fy->closed_by,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'start_date_jalali' => sprintf('%d/01/01', $year),
                'end_date_jalali' => sprintf('%d/12/29', $year),
                'is_current_context' => (int) $fy->legacy_company_id === $company,
                'pl_document_id' => $fy->pl_document_id,
                'closing_document_id' => $fy->closing_document_id,
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
                    'company_id' => $company,
                    'current_fiscal_year_id' => $currentFiscalYear->id,
                    'count' => $total,
                    'open_count' => $mapped->where('is_closed', false)->count(),
                    'closed_count' => $mapped->where('is_closed', true)->count(),
                    'note' => __('Each fiscal year is a separate company workspace. Use the company_id value as {company} in other API routes.'),
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
