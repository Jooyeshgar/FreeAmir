<?php

namespace App\Http\Controllers\Api;

use App\Enums\CustomerType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCustomerRequest;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerService $customerService
    ) {}

    public function index(Request $request, $company): JsonResponse
    {
        $validated = $request->validate([
            'type' => [
                'nullable',
                Rule::in(CustomerType::valueNames()),
            ],
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Customer::query()
            ->orderBy('name')
            ->orderBy('id');

        if (! empty($validated['type'])) {
            $query->where(
                'type',
                CustomerType::fromName($validated['type'])->value
            );
        }

        if (! empty($validated['q'])) {
            $search = trim($validated['q']);

            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('personal_code', 'like', "%{$search}%")
                    ->orWhere('ecnmcs_code', 'like', "%{$search}%");
            });
        }

        $customers = $query->paginate(
            $validated['per_page'] ?? 20,
            [
                'id',
                'name',
                'type',
                'mobile',
                'phone',
                'personal_code',
                'ecnmcs_code',
                'balance',
                'credit',
                'created_at',
                'updated_at',
            ],
            'page',
            $validated['page'] ?? 1
        );

        return response()->json($customers);
    }

    public function show($company, $id): JsonResponse
    {
        $customer = Customer::query()
            ->with([
                'subject',
                'group',
                'introducer:id,name',
            ])
            ->findOrFail($id);

        return response()->json([
            'data' => $customer,
        ]);
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $duplicate = Customer::query()
            ->where(function ($query) use ($validated) {
                $hasCondition = false;

                if (isset($validated['personal_code']) && trim($validated['personal_code']) !== '') {
                    $query->orWhere('personal_code', trim($validated['personal_code']));
                    $hasCondition = true;
                }

                if (isset($validated['ecnmcs_code']) && trim($validated['ecnmcs_code']) !== '') {
                    $query->orWhere('ecnmcs_code', trim($validated['ecnmcs_code']));
                    $hasCondition = true;
                }

                if (! $hasCondition) {
                    $query->whereRaw('1 = 0');
                }
            })
            ->first(['id', 'name', 'personal_code', 'ecnmcs_code']);

        if ($duplicate) {
            return response()->json([
                'message' => __('A customer with the provided national or economic code already exists.'),
                'existing_customer' => $duplicate,
            ], 409);
        }

        $validated['type'] = CustomerType::fromName($validated['type']);

        $customer = $this->customerService->create($validated);
        $customer->refresh();

        return response()->json([
            'data' => $customer,
        ], 201);
    }
}
