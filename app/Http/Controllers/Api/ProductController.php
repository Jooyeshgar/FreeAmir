<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request, int $fiscalYear): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'group_id' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $products = Product::query()
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
            'data' => $products->items(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'last_page' => $products->lastPage(),
            ],
        ]);
    }

    public function store(Request $request, ProductService $productService, int $fiscalYear): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100'],
            'sstid' => ['nullable', 'string', 'max:100'],
            'group' => ['required', 'integer', 'exists:product_groups,id'],
            'location' => ['nullable', 'string', 'max:255'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'quantity_warning' => ['nullable', 'numeric', 'min:0'],
            'oversell' => ['nullable', 'boolean'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'discount_formula' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'vat' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'average_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $group = ProductGroup::query()->findOrFail($data['group']);
        $data['fiscal_year_id'] = getActiveFiscalYear();

        $existing = Product::query()->where('code', $data['code'])->first();
        if ($existing) {
            if ($existing->name === $data['name'] && $existing->group == $group->id) {
                return response()->json(['data' => $existing->load('productGroup'), 'reused' => true]);
            }

            return response()->json([
                'message' => __('The product code is already in use.'),
                'errors' => ['code' => [__('The product code is already in use.')]],
            ], 409);
        }

        $data['quantity'] ??= 0;
        $data['quantity_warning'] ??= 0;
        $data['oversell'] ??= false;
        $data['selling_price'] ??= 0;
        $data['vat'] ??= 0;
        $data['average_cost'] ??= 0;

        $product = DB::transaction(fn () => $productService->create($data));

        return response()->json([
            'data' => $product->fresh()->load('productGroup'),
            'message' => __('Product created successfully.'),
        ], 201);
    }
}
