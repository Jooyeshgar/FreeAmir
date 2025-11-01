<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models;
use App\Services\ProductService;

class ProductController extends Controller
{
    public function __construct() {}

    public function index()
    {
        $products = Models\Product::with('productGroup')->paginate(12);

        return view('products.index', compact('products'));
    }

    public function create()
    {
        $groups = Models\ProductGroup::select('id', 'name')->get();

        return view('products.create', compact('groups'));
    }

    public function store(StoreProductRequest $request)
    {
        $validatedData = $request->getValidatedData();

        ProductService::create($validatedData);

        return redirect()->route('products.index')->with('success', __('Product created successfully.'));
    }

    public function edit(Models\Product $product)
    {
        $groups = Models\ProductGroup::select('id', 'name', 'sstid')->get();

        return view('products.edit', compact('product', 'groups'));
    }

    public function update(UpdateProductRequest $request, Models\Product $product, ProductService $productService)
    {
        $validatedData = $request->getValidatedData();

        $productService->update($product, $validatedData);

        return redirect()->route('products.index')->with('success', __('Product updated successfully.'));
    }

    public function show(Models\Product $product)
    {
        $product->load('productgroup', 'invoiceItems.invoice');

        return view('products.show', compact('product'));
    }

    public function destroy(Models\Product $product, ProductService $productService)
    {
        $productService->delete($product);

        return redirect()->route('products.index')->with('success', __('Product deleted successfully.'));
    }
}
