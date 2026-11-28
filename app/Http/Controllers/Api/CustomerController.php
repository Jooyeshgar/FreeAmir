<?php

namespace App\Http\Controllers\Api;

use App\Enums\CustomerType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCustomerRequest;
use App\Models\Customer;
use App\Models\FiscalYear;
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

        $customers = CustomerService::searchQuery([
            'q' => $validated['q'] ?? '',
            'type' => $validated['type'] ?? null,
        ])
            ->paginate($validated['per_page'] ?? 20, ['*'], 'page', $validated['page'] ?? 1);

        return response()->json([
            'data' => collect($customers->items())
                ->map(fn (Customer $customer) => CustomerService::formatSummary($customer))
                ->all(),
            'meta' => [
                'current_page' => $customers->currentPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
                'last_page' => $customers->lastPage(),
                'from' => $customers->firstItem(),
                'to' => $customers->lastItem(),
            ],
        ]);
    }

    public function show($company, $id): JsonResponse
    {
        $customer = Customer::query()
            ->with([
                'group:id,name',
                'subject',
                'introducer:id,name',
            ])
            ->findOrFail($id);

        $currency = (string) (FiscalYear::query()->find(getActiveFiscalYear())?->company?->currency ?: 'Rial');

        return response()->json([
            'data' => CustomerService::formatDetail($customer, $currency),
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
