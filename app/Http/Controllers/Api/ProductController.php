<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    /**
     * لیست کالاهای شرکت
     *
     * Query Parameters:
     * - q: جستجو در نام یا کد کالا
     * - group_id: فیلتر گروه کالا
     * - per_page: تعداد نتایج هر صفحه (حداکثر 100)
     * - page: شماره صفحه
     */

    public function index(Request $request, $company): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'group_id' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = Product::query()
            ->where('company_id', $company);

        // فیلتر گروه کالا
        if (!empty($validated['group_id'])) {
            $query->where('group', $validated['group_id']);
        }

        // جستجو در نام یا کد کالا
        if (!empty($validated['q'])) {
            $search = trim($validated['q']);

            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        // دریافت کالاها با صفحه‌بندی
        $products = $query
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'data' => $products->items(),

            'meta' => [
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'last_page' => $products->lastPage(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
            ],

            'links' => [
                'first' => $products->url(1),
                'last' => $products->url($products->lastPage()),
                'prev' => $products->previousPageUrl(),
                'next' => $products->nextPageUrl(),
            ],
        ]);
    }
    public function store(Request $request, ProductService $productService): JsonResponse
    {
        $input = $request->all();

        // Product groups are accounting classifications. Use only an explicitly configured
        // default; never invent a group ID.
        if (empty($input['group'])) {
            $defaultGroupId = (int) env('FREEAMIR_DEFAULT_PRODUCT_GROUP_ID', 0);
            if ($defaultGroupId > 0) {
                $input['group'] = $defaultGroupId;
            }
        }

        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100'],
            'sstid' => ['nullable', 'string', 'max:100'],
            'group' => ['required', 'integer', 'min:1'],
            'location' => ['nullable', 'string', 'max:255'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'quantity_warning' => ['nullable', 'numeric', 'min:0'],
            'oversell' => ['nullable', 'boolean'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'discount_formula' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'vat' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'average_cost' => ['nullable', 'numeric', 'min:0'],
        ])->validate();

        $companyId = (int) getActiveLegacyCompany();
        $group = ProductGroup::query()
            ->where('company_id', $companyId)
            ->find((int) $data['group']);

        if (! $group) {
            return response()->json([
                'message' => 'گروه کالای تعیین‌شده در شرکت/سال مالی جاری پیدا نشد.',
                'errors' => ['group' => ['شناسه گروه کالا معتبر نیست.']],
            ], 422);
        }

        $name = trim($data['name']);
        $code = trim($data['code']);
        $sstid = trim((string) ($data['sstid'] ?? ''));

        // Deterministic code makes this endpoint safe to call after a lost response.
        $existingByCode = Product::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->first();

        if ($existingByCode) {
            $sameIdentity = trim((string) $existingByCode->name) === $name
                && trim((string) ($existingByCode->sstid ?? '')) === $sstid;

            if ($sameIdentity) {
                return response()->json(['data' => $existingByCode->load('productGroup'), 'reused' => true], 200);
            }

            return response()->json([
                'message' => 'کد داخلی کالای ارسالی قبلاً برای کالای دیگری استفاده شده است.',
                'errors' => ['code' => ['کد داخلی تکراری است.']],
            ], 409);
        }

        $existingQuery = Product::query()
            ->where('company_id', $companyId)
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
            return response()->json(['data' => $existingByIdentity->load('productGroup'), 'reused' => true], 200);
        }

        $data['name'] = $name;
        $data['code'] = $code;
        $data['sstid'] = $sstid !== '' ? $sstid : null;
        $data['quantity'] = $data['quantity'] ?? 0;
        $data['quantity_warning'] = $data['quantity_warning'] ?? 0;
        $data['oversell'] = $data['oversell'] ?? 0;
        $data['selling_price'] = $data['selling_price'] ?? 0;
        $data['vat'] = $data['vat'] ?? 0;
        $data['average_cost'] = $data['average_cost'] ?? 0;
        $data['company_id'] = $companyId;

        $product = DB::transaction(function () use ($productService, $data) {
            return $productService->create($data);
        });

        return response()->json([
            'data' => $product->fresh()->load('productGroup'),
            'message' => 'محصول با موفقیت ایجاد شد.',
            'reused' => false,
        ], 201);
    }
}
