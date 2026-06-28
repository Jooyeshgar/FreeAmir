<?php

namespace App\Http\Controllers\Api;

use App\Filters\ApiEmployeeFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreEmployeeRequest;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;

class EmployeeController extends Controller
{
    public function index(ApiEmployeeFilter $filter): JsonResponse
    {
        $employees = Employee::filter($filter)
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
