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
            ->companies()
            ->orderBy('name')
            ->get(['companies.id', 'name', 'fiscal_year', 'currency', 'closed_at'])
            ->map(fn ($company) => [
                'id' => $company->id,
                'name' => $company->name,
                'fiscal_year' => $company->fiscal_year,
                'currency' => $company->currency,
                'closed_at' => $company->closed_at,
            ]);

        return response()->json(['data' => $companies]);
    }
}
