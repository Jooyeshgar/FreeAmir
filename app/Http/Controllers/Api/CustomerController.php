<?php

namespace App\Http\Controllers\Api;

use App\Enums\CustomerType;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $customerService) {}

    public function index(Request $request, int $fiscalYear): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', Rule::in(CustomerType::valueNames())],
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $customers = Customer::query()
            ->when(! empty($validated['type']), fn ($query) => $query->where('type', CustomerType::fromName($validated['type'])->value))
            ->when(! empty($validated['q']), function ($query) use ($validated) {
                $search = trim($validated['q']);
                $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('personal_code', 'like', "%{$search}%")
                    ->orWhere('ecnmcs_code', 'like', "%{$search}%"));
            })
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($validated['per_page'] ?? 20, ['*'], 'page', $validated['page'] ?? 1);

        return response()->json($customers);
    }

    public function show(Request $request, int $fiscalYear, int $id): JsonResponse
    {
        $customer = Customer::query()
            ->with(['subject', 'group', 'introducer:id,name'])
            ->findOrFail($id);

        return response()->json(['data' => $customer]);
    }

    public function store(Request $request, int $fiscalYear): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(CustomerType::valueNames())],
            'group_id' => ['required', 'integer', Rule::exists('customer_groups', 'id')->where('fiscal_year_id', getActiveFiscalYear())],
            'subject_code' => ['nullable', 'string', 'max:20', 'regex:/^[0-9]+$/'],
            'mobile' => ['nullable', 'string', 'max:15'],
            'phone' => ['nullable', 'string', 'max:15'],
            'fax' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:64'],
            'address' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:15'],
            'personal_code' => ['nullable', 'string', 'max:15'],
            'ecnmcs_code' => ['nullable', 'string', 'max:20'],
            'web_page' => ['nullable', 'string', 'max:50'],
            'responsible' => ['nullable', 'string', 'max:50'],
            'connector' => ['nullable', 'string', 'max:50'],
            'desc' => ['nullable', 'string', 'max:150'],
            'rep_via_email' => ['sometimes', 'boolean'],
            'acc_name_1' => ['nullable', 'string', 'max:50'],
            'acc_no_1' => ['nullable', 'string', 'max:30'],
            'acc_bank_1' => ['nullable', 'string', 'max:50'],
            'acc_name_2' => ['nullable', 'string', 'max:50'],
            'acc_no_2' => ['nullable', 'string', 'max:30'],
            'acc_bank_2' => ['nullable', 'string', 'max:50'],
        ]);

        $duplicate = null;
        if (! empty($data['personal_code']) || ! empty($data['ecnmcs_code'])) {
            $duplicate = Customer::query()
                ->where(function ($query) use ($data) {
                    if (! empty($data['personal_code'])) {
                        $query->orWhere('personal_code', trim($data['personal_code']));
                    }
                    if (! empty($data['ecnmcs_code'])) {
                        $query->orWhere('ecnmcs_code', trim($data['ecnmcs_code']));
                    }
                })
                ->first();
        }

        if ($duplicate) {
            throw ValidationException::withMessages([
                'personal_code' => __('A customer with the provided national or economic code already exists.'),
            ]);
        }

        CustomerGroup::query()->findOrFail($data['group_id']);
        $data['fiscal_year_id'] = getActiveFiscalYear();
        $data['type'] = CustomerType::fromName($data['type']);

        $customer = $this->customerService->create($data);

        return response()->json(['data' => $customer->refresh()], 201);
    }
}
