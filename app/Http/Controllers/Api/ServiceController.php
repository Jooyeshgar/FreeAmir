<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreServiceRequest;
use App\Models\Service;
use App\Services\ServiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index(Request $request, $company): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'group_id' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $services = ServiceService::searchQuery([
            'q' => $validated['q'] ?? '',
            'group_id' => $validated['group_id'] ?? null,
        ])
            ->paginate($validated['per_page'] ?? 20, ['*'], 'page', $validated['page'] ?? 1);

        return response()->json([
            'data' => collect($services->items())
                ->map(fn (Service $service) => ServiceService::formatSummary($service))
                ->all(),
            'meta' => [
                'current_page' => $services->currentPage(),
                'per_page' => $services->perPage(),
                'total' => $services->total(),
                'last_page' => $services->lastPage(),
                'from' => $services->firstItem(),
                'to' => $services->lastItem(),
            ],
        ]);
    }

    public function store(StoreServiceRequest $request, ServiceService $serviceService): JsonResponse
    {
        $result = $serviceService->storeReusable($request->validated());

        if (isset($result['conflict'])) {
            return response()->json([
                'message' => __('The service code is already used by another service.'),
                'errors' => ['code' => [__('The service code has already been taken.')]],
            ], 409);
        }

        if ($result['reused']) {
            return response()->json([
                'data' => $result['service']->load('serviceGroup'),
                'reused' => true,
            ], 200);
        }

        return response()->json([
            'data' => $result['service']->load('serviceGroup'),
            'message' => __('Service created successfully.'),
            'reused' => false,
        ], 201);
    }
}
