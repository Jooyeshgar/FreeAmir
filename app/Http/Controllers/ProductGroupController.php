<?php

namespace App\Http\Controllers;

use App\Models;
use Illuminate\Http\Request;

class ProductGroupController extends Controller
{
    public function __construct() {}

    public function index()
    {
        $productGroups = Models\ProductGroup::paginate(12);

        return view('productGroups.index', compact('productGroups'));
    }

    public function create()
    {
        if (empty(config('amir.product'))) {
            return redirect()->route('configs.index')->with('error', __('Product Subject is not configured. Please set it in configurations.'));
        }
        return view('productGroups.create');
    }

    public function store(Request $request)
    {
        // TODO validate request
        $validatedData = $request->validate([
            'name' => 'required|max:20|string|regex:/^[\w\d\s]*$/u',
            'vat' => 'nullable|numeric|min:0|max:100',
        ]);

        Models\ProductGroup::create($validatedData);

        return redirect()->route('product-groups.index')->with('success', __('Product group created successfully.'));
    }

    public function edit(Models\ProductGroup $productGroup)
    {
        return view('productGroups.edit', compact('productGroup'));
    }

    public function update(Request $request, Models\ProductGroup $productGroup)
    {
        // TODO validate request
        $validatedData = $request->validate([
            'code' => 'required|unique:product_groups,code,' . $productGroup->id . ',|regex:/^\d{3}$/',
            'name' => 'required|max:20|string|regex:/^[\w\d\s]*$/u',
            'vat' => 'nullable|numeric|min:0|max:100',
        ]);

        $productGroup->update($validatedData);

        return redirect()->route('product-groups.index')->with('success', __('Product group updated successfully.'));
    }

    public function destroy(Models\ProductGroup $productGroup)
    {
        $productGroup->delete();

        return redirect()->route('product-groups.index')->with('success', __('Product group deleted successfully.'));
    }
}
