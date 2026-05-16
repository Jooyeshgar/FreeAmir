<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreEmployeeRequest;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $employees = Employee::query()
            ->when($request->has('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('code')
            ->get(['id', 'code', 'first_name', 'last_name', 'device_id', 'is_active']);

        return response()->json(['data' => $employees]);
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = Employee::create(array_merge(
            $request->validated(),
            ['company_id' => getActiveCompany()]
        ));

        return response()->json(['data' => $employee], 201);
    }
}
