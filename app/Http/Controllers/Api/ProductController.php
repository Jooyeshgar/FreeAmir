<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreProductRequest;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = Product::query();

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

    public function store(StoreProductRequest $request, ProductService $productService): JsonResponse
    {
        $data = $request->validated();

        $group = ProductGroup::query()
            ->find((int) $data['group']);

        if (! $group) {
            return response()->json([
                'message' => __('The selected product group was not found in the current fiscal year.'),
                'errors' => ['group' => [__('The product group ID is invalid.')]],
            ], 422);
        }

        $name = trim($data['name']);
        $code = trim($data['code']);
        $sstid = trim((string) ($data['sstid'] ?? ''));

        $existingByCode = Product::query()
            ->where('code', $code)
            ->first();

        if ($existingByCode) {
            $sameIdentity = trim((string) $existingByCode->name) === $name
                && trim((string) ($existingByCode->sstid ?? '')) === $sstid;

            if ($sameIdentity) {
                return response()->json(['data' => $existingByCode->load('productGroup'), 'reused' => true], 200);
            }

            return response()->json([
                'message' => __('The product code is already used by another product.'),
                'errors' => ['code' => [__('The product code has already been taken.')]],
            ], 409);
        }

        $existingQuery = Product::query()
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

        $product = DB::transaction(function () use ($productService, $data) {
            return $productService->create($data);
        });

        return response()->json([
            'data' => $product->fresh()->load('productGroup'),
            'message' => __('Product created successfully.'),
            'reused' => false,
        ], 201);
    }
}
