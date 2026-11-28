<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreProductRequest;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request, $company): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'group_id' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $products = ProductService::searchQuery([
            'q' => $validated['q'] ?? '',
            'group_id' => $validated['group_id'] ?? null,
        ])
            ->paginate($validated['per_page'] ?? 20, ['*'], 'page', $validated['page'] ?? 1);

        return response()->json([
            'data' => collect($products->items())
                ->map(fn (Product $product) => ProductService::formatSummary($product))
                ->all(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'last_page' => $products->lastPage(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
            ],
        ]);
    }

    public function store(StoreProductRequest $request, ProductService $productService): JsonResponse
    {
        $result = $productService->storeReusable($request->validated());

        if (isset($result['conflict'])) {
            return response()->json([
                'message' => __('The product code is already used by another product.'),
                'errors' => ['code' => [__('The product code has already been taken.')]],
            ], 409);
        }

        if ($result['reused']) {
            return response()->json([
                'data' => $result['product']->load('productGroup'),
                'reused' => true,
            ], 200);
        }

        return response()->json([
            'data' => $result['product']->load('productGroup'),
            'message' => __('Product created successfully.'),
            'reused' => false,
        ], 201);
    }
}
