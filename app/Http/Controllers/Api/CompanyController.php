<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companies = $request->user()
            ->fiscalYears()
            ->with('company:id,name,currency')
            ->orderByDesc('year')
            ->orderBy('fiscal_years.id')
            ->get(['fiscal_years.id', 'company_id', 'year', 'closed_at'])
            ->map(fn ($year) => [
                'id' => $year->id,
                'company_id' => $year->company_id,
                'fiscal_year_id' => $year->id,
                'name' => $year->company->name,
                'fiscal_year' => $year->year,
                'currency' => $year->company->currency,
                'closed_at' => $year->closed_at,
            ]);

        return response()->json(['data' => $companies]);
    }
}
