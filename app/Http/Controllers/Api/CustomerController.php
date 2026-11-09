<?php

namespace App\Http\Controllers\Api;

use App\Enums\CustomerType;
use App\Services\CustomerService;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(
    private readonly CustomerService $customerService
) {}
    /**
     * لیست مشتریان و تأمین‌کنندگان
     *
     * Query params:
     * - type (optional): customer | supplier | both
     * - q (optional): جستجو در نام، کد، موبایل، کد ملی، کد اقتصادی
     * - is_active (optional): 0|1 (اگر فیلد is_active داشته باشی)
     */

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
            ->where('company_id', $company)
            ->orderBy('name')
            ->orderBy('id');

        if (!empty($validated['type'])) {
            $query->where(
                'type',
                CustomerType::fromName($validated['type'])->value
            );
        }

        if (!empty($validated['q'])) {
            $search = trim($validated['q']);

            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('personal_code', 'like', "%{$search}%")
                    ->orWhere('ecnmcs_code', 'like', "%{$search}%");
            });
        }

        $customers = $query->paginate(
            $validated['per_page'] ?? 20,
            [
                'id',
                'code',
                'name',
                'type',
                'mobile',
                'phone',
                'personal_code',
                'ecnmcs_code',
                'balance',
                'credit',
                'created_at',
            ],
            'page',
            $validated['page'] ?? 1
        );

        return response()->json($customers);
    }

        /**
     * جزئیات یک مشتری / تأمین‌کننده
     */

    public function show(Request $request, $company, $id): JsonResponse
    {
        $customer = Customer::query()
            ->where('company_id', $company)
            ->with([
                'subject',
                'group',
                'introducer:id,name,code',
            ])
            ->where('id', $id)
            ->firstOrFail([
                'id',
                'company_id',
                'fiscal_year_id',
                'code',
                'name',
                'type',
                'subject_id',
                'group_id',
                'introducer_id',

                // اطلاعات تماس
                'mobile',
                'phone',
                'fax',
                'email',
                'address',
                'postal_code',
                'web_page',

                // اطلاعات هویتی و تکمیلی
                'personal_code',
                'ecnmcs_code',
                'responsible',
                'connector',
                'desc',

                // اطلاعات مالی
                'balance',
                'credit',
                'commission',
                'disc_rate',

                // اطلاعات بانکی
                'acc_name_1',
                'acc_no_1',
                'acc_bank_1',
                'acc_name_2',
                'acc_no_2',
                'acc_bank_2',

                // سایر مشخصات
                'rep_via_email',
                'marked',
                'reason',
                'created_at',
                'updated_at',
            ]);

        return response()->json([
            'data' => $customer,
        ]);
    }



        /**
     * ایجاد مشتری / تأمین‌کننده
     */

    public function store(Request $request, $company): JsonResponse
    {
        $fiscalYearId = getActiveFiscalYear();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:50'],
            'type' => [
                'required',
                Rule::in(CustomerType::valueNames()),
            ],
            'group_id' => [
                'required',
                'integer',
                Rule::exists('customer_groups', 'id')
                    ->where('company_id', $company)
                    ->where('fiscal_year_id', $fiscalYearId),
            ],
            'subject_code' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^[0-9]+$/',
            ],
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

        // جلوگیری از ثبت مشتری تکراری در همان شرکت
        
        $duplicateQuery = Customer::query()
            ->where('company_id', $company)
            ->where(function ($query) use ($validated) {
                $hasCondition = false;

                if (isset($validated['code']) && trim($validated['code']) !== '') {
                    $query->orWhere('code', trim($validated['code']));
                    $hasCondition = true;
                }
                if (isset($validated['personal_code']) && trim($validated['personal_code']) !== '') {
                    $query->orWhere('personal_code', trim($validated['personal_code']));
                    $hasCondition = true;
                }

                if (isset($validated['ecnmcs_code']) && trim($validated['ecnmcs_code']) !== '') {
                    $query->orWhere('ecnmcs_code', trim($validated['ecnmcs_code']));
                    $hasCondition = true;
                }

                if (!$hasCondition) {
                    $query->whereRaw('1 = 0');
                }
            });

        $duplicate = $duplicateQuery->first([
            'id',
            'name',
            'code',
            'personal_code',
            'ecnmcs_code',
        ]);



        $duplicate = $duplicateQuery->first([
            'id',
            'name',
            'code',
            'personal_code',
            'ecnmcs_code',
        ]);

        if ($duplicate) {
            return response()->json([
                'message' => 'مشتری با کد ملی یا شناسه اقتصادی واردشده قبلاً ثبت شده است.',
                'existing_customer' => [
                    'id' => $duplicate->id,
                    'name' => $duplicate->name,
                    'code' => $duplicate->code,
                ],
            ], 409);
        }

        $validated['company_id'] = $company;
        $validated['type'] = CustomerType::fromName($validated['type']);

        $customer = $this->customerService->create($validated);
        $customer->refresh();

        return response()->json([
            'data' => $customer,
        ], 201);
    }
}