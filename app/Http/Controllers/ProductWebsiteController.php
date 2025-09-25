<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductWebsite;
use Illuminate\Http\Request;

class ProductWebsiteController extends Controller
{
    public function __construct() {}

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'link' => 'required|regex:/^(https?:\/\/)?([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,6}(\/[^\s]*)?$/',
            'product_id' => 'required|exists:products,id',
        ]);

        ProductWebsite::create($validatedData);

        return redirect()->back()->with('success', __('product website successfully.'));
    }

    public function update(Request $request, ProductWebsite $productWebsite)
    {
        $validatedData = $request->validate([
            'link' => 'required|regex:/^(https?:\/\/)?([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,6}(\/[^\s]*)?$/',
            'product_id' => 'required|exists:products,id',
        ]);

        $productWebsite->update($validatedData);

        return redirect()->back()->with('success', __('product website updated successfully.'));
    }

    public function destroy(ProductWebsite $productWebsite)
    {
        $productWebsite->delete();
        return redirect()->back()->with('success', __('product website deleted successfully.'));
    }
}