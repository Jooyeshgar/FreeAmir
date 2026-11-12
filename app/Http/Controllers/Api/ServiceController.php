<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceGroup;
use App\Services\ServiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServiceController extends Controller
{
    public function index(Request $request, int $fiscalYear): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'group_id' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $services = Service::query()
            ->when(isset($validated['group_id']), fn ($query) => $query->where('group', $validated['group_id']))
            ->when(! empty($validated['q']), function ($query) use ($validated) {
                $search = trim($validated['q']);
                $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%"));
            })
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($validated['per_page'] ?? 20, ['*'], 'page', $validated['page'] ?? 1);

        return response()->json([
            'data' => $services->items(),
            'meta' => [
                'current_page' => $services->currentPage(),
                'per_page' => $services->perPage(),
                'total' => $services->total(),
                'last_page' => $services->lastPage(),
            ],
        ]);
    }

    public function store(Request $request, ServiceService $serviceService, int $fiscalYear): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100'],
            'sstid' => ['nullable', 'string', 'max:100'],
            'group' => ['required', 'integer', 'exists:service_groups,id'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:150'],
            'vat' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $group = ServiceGroup::query()->findOrFail($data['group']);
        $data['fiscal_year_id'] = getActiveFiscalYear();

        $existing = Service::query()->where('code', $data['code'])->first();
        if ($existing) {
            if ($existing->name === $data['name'] && $existing->group == $group->id) {
                return response()->json(['data' => $existing->load('serviceGroup'), 'reused' => true]);
            }

            return response()->json([
                'message' => __('The service code is already in use.'),
                'errors' => ['code' => [__('The service code is already in use.')]],
            ], 409);
        }

        $data['selling_price'] ??= 0;
        $data['vat'] ??= 0;
        $service = DB::transaction(fn () => $serviceService->create($data));

        return response()->json([
            'data' => $service->fresh()->load('serviceGroup'),
            'message' => __('Service created successfully.'),
        ], 201);
    }
}
