<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models;

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

        $product = Models\Product::create($validatedData);
        if (isset($validatedData['websites'])) {
            foreach ($validatedData['websites'] as $website) {
                Models\ProductWebsite::create([
                    'link' => $website['link'],
                    'product_id' => $product->id,
                ]);
            }
        }

        return redirect()->route('products.index')->with('success', __('Product created successfully.'));
    }

    public function edit(Models\Product $product)
    {
        $groups = Models\ProductGroup::select('id', 'name', 'sstid')->get();

        return view('products.edit', compact('product', 'groups'));
    }

    public function update(UpdateProductRequest $request, Models\Product $product)
    {
        $validatedData = $request->getValidatedData();

        $product->productWebsites()->delete();

        if (isset($validatedData['websites'])) {
            foreach ($validatedData['websites'] as $website) {
                Models\ProductWebsite::create([
                    'link' => $website['link'],
                    'product_id' => $product->id,
                ]);
            }
        }

        $product->update($validatedData);

        return redirect()->route('products.index')->with('success', __('Product updated successfully.'));
    }

    public function show(Models\Product $product)
    {
        $product->load('productgroup');

        $invoices = [];
        $invoice_items = Models\InvoiceItem::where('product_id', $product->id)->orderBy('updated_at')->get();

        if ($invoice_items->count() > 0) {
            foreach ($invoice_items as $invoice_item) {
                $invoice_item['is_sell'] = Models\Invoice::select('is_sell')->find($invoice_item->invoice_id)->is_sell;
            }
        }

        return view('products.show', compact('product', 'invoice_items'));
    }

    public function destroy(Models\Product $product)
    {
        $product->delete();

        return redirect()->route('products.index')->with('success', __('Product deleted successfully.'));
    }
}
