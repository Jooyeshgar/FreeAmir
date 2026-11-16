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
    public function index(Request $request, $company): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'group_id' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = Service::query();

        if (! empty($validated['group_id'])) {
            $query->where('group', $validated['group_id']);
        }

        if (! empty($validated['q'])) {
            $search = trim($validated['q']);

            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $services = $query
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'data' => $services->items(),

            'meta' => [
                'current_page' => $services->currentPage(),
                'per_page' => $services->perPage(),
                'total' => $services->total(),
                'last_page' => $services->lastPage(),
                'from' => $services->firstItem(),
                'to' => $services->lastItem(),
            ],

            'links' => [
                'first' => $services->url(1),
                'last' => $services->url($services->lastPage()),
                'prev' => $services->previousPageUrl(),
                'next' => $services->nextPageUrl(),
            ],
        ]);
    }

    public function store(Request $request, ServiceService $serviceService): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100'],
            'sstid' => ['nullable', 'string', 'max:100'],
            'group' => ['required', 'integer', 'min:1'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:150'],
            'vat' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $group = ServiceGroup::query()
            ->find((int) $data['group']);

        if (! $group) {
            return response()->json([
                'message' => __('The selected service group was not found in the current fiscal year.'),
                'errors' => ['group' => [__('The service group ID is invalid.')]],
            ], 422);
        }

        $name = trim($data['name']);
        $code = trim($data['code']);
        $sstid = trim((string) ($data['sstid'] ?? ''));

        $existingByCode = Service::query()
            ->where('code', $code)
            ->first();

        if ($existingByCode) {
            $sameIdentity = trim((string) $existingByCode->name) === $name
                && trim((string) ($existingByCode->sstid ?? '')) === $sstid;

            if ($sameIdentity) {
                return response()->json(['data' => $existingByCode->load('serviceGroup'), 'reused' => true], 200);
            }

            return response()->json([
                'message' => __('The service code is already used by another service.'),
                'errors' => ['code' => [__('The service code has already been taken.')]],
            ], 409);
        }

        $existingQuery = Service::query()
            ->where('name', $name)
            ->where('group', (int) $data['group']);

        if ($sstid !== '') {
            $existingQuery->where('sstid', $sstid);
        } else {
            $existingQuery->where(function ($q) {
                $q->whereNull('sstid')->orWhere('sstid', '');
            });
        }

        $existingByIdentity = $existingQuery->first();

        if ($existingByIdentity) {
            return response()->json(['data' => $existingByIdentity->load('serviceGroup'), 'reused' => true], 200);
        }

        $data['name'] = $name;
        $data['code'] = $code;
        $data['sstid'] = $sstid !== '' ? $sstid : null;
        $data['selling_price'] = $data['selling_price'] ?? 0;
        $data['vat'] = $data['vat'] ?? 0;

        $service = DB::transaction(function () use ($serviceService, $data) {
            return $serviceService->create($data);
        });

        return response()->json([
            'data' => $service->fresh()->load('serviceGroup'),
            'message' => __('Service created successfully.'),
            'reused' => false,
        ], 201);
    }
}
