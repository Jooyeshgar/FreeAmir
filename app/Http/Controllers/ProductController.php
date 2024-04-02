<?php

namespace App\Http\Controllers;

use App\Models;
use Illuminate\Http\Request;

class ProductController extends Controller
{
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

    public function store(Request $request)
    {
        // TODO validate request
        $validatedData = $request->validate([
            'code' => 'required|unique:products,code',
            'name' => 'required|max:20|string|regex:/^[\w\d\s]*$/u',
            'group' => 'required|exists:product_groups,id|integer',
            'location' => 'nullable|max:50|string|regex:/^[\w\d\s]*$/u',
            'quantity' => 'required|min:0|numeric',
            'quantity_warning' => 'nullable|min:0|numeric',
            'oversell' => 'nullable|in:on,off',
            'purchace_price' => 'required|min:0|numeric',
            'selling_price' => 'required|min:0|numeric',
            'discount_formula' => 'nullable|max:50|string|regex:/^[\w\d\s]*$/u',
            'description' => 'nullable|max:150|string|regex:/^[\w\d\s]*$/u'
        ]);

        $validatedData['oversell'] = $request->has('oversell') ? 1 : 0;

        Models\Product::create($validatedData);

        return redirect()->route('products.index')->with('success', 'Product created successfully.');
    }

    public function edit(Models\Product $product)
    {
        $groups = Models\ProductGroup::select('id', 'name')->get();
        return view('products.edit', compact('product', 'groups'));
    }

    public function update(Request $request, Models\Product $product)
    {
        // TODO validate request
        $validatedData = $request->validate([
            'code' => 'required|exists:products,code',
            'name' => 'required|max:20|string|regex:/^[\w\d\s]*$/u',
            'group' => 'required|exists:product_groups,id|integer',
            'location' => 'nullable|max:50|string|regex:/^[\w\d\s]*$/u',
            'quantity' => 'required|min:0|numeric',
            'quantity_warning' => 'nullable|min:0|numeric',
            'oversell' => 'nullable|in:on,off',
            'purchace_price' => 'required|min:0|numeric',
            'selling_price' => 'required|min:0|numeric',
            'discount_formula' => 'nullable|max:50|string|regex:/^[\w\d\s]*$/u',
            'description' => 'nullable|max:150|string|regex:/^[\w\d\s]*$/u'
        ]);

        $validatedData['oversell'] = $request->has('oversell') ? 1 : 0;

        $product->update($validatedData);

        return redirect()->route('products.index')->with('success', 'Product updated successfully.');
    }

    public function destroy(Models\Product $product)
    {
        $product->delete();

        return redirect()->route('products.index')->with('success', 'Product deleted successfully.');
    }

}
