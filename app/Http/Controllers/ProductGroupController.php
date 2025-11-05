<?php

namespace App\Http\Controllers;

use App\Models;
use App\Services\ProductGroupService;
use Illuminate\Http\Request;

class ProductGroupController extends Controller
{
    public function __construct(
        private readonly ProductGroupService $productGroupService,
    ) {}

    public function index()
    {
        $productGroups = Models\ProductGroup::paginate(12);

        return view('productGroups.index', compact('productGroups'));
    }

    public function create()
    {
        if (empty(config('amir.inventory'))) {
            return redirect()->route('configs.index')->with('error', __('Inventory Subject is not configured. Please set it in configurations.'));
        }

        return view('productGroups.create');
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|max:20|string|regex:/^[\w\d\s]*$/u',
            'vat' => 'nullable|numeric|min:0|max:100',
            'sstid' => 'nullable|string',
        ]);

        $this->productGroupService->create($validatedData);

        return redirect()->route('product-groups.index')->with('success', __('Product group created successfully.'));
    }

    public function edit(Models\ProductGroup $productGroup)
    {
        return view('productGroups.edit', compact('productGroup'));
    }

    public function update(Request $request, Models\ProductGroup $productGroup)
    {
        $validatedData = $request->validate([
            'name' => 'required|max:20|string|regex:/^[\w\d\s]*$/u',
            'vat' => 'nullable|numeric|min:0|max:100',
            'sstid' => 'nullable|string',
        ]);

        $this->productGroupService->update($productGroup, $validatedData);

        return redirect()->route('product-groups.index')->with('success', __('Product group updated successfully.'));
    }

    public function destroy(Models\ProductGroup $productGroup)
    {
        $this->productGroupService->delete($productGroup);

        return redirect()->route('product-groups.index')->with('success', __('Product group deleted successfully.'));
    }
}
