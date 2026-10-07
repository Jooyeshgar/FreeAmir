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
            ->with('company')
            ->orderBy('year')
            ->get()
            ->map(fn ($fiscalYear) => [
                'id' => $fiscalYear->id,
                'name' => $fiscalYear->company?->name,
                'fiscal_year' => $fiscalYear->year,
                'currency' => $fiscalYear->company?->currency,
                'closed_at' => $fiscalYear->closed_at,
            ]);

        return response()->json(['data' => $companies]);
    }
}
