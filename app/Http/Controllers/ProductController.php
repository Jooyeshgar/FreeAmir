<?php

namespace App\Http\Controllers;

use App\Models;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {
        $products = Models\Product::with('group')->paginate(12);
        $cols = [
            'code', 'name', 'group', 'location', 'quantity',
            'quantity_warning', 'oversell', 'purchace_price',
            'selling_price', 'discount_formula', 'description'
        ];
        return view('products.index', compact('products', 'cols'));
    }

    public function create()
    {
        $groups = Models\ProductGroup::select('id', 'name')->get();
        $fields = $this->fields($groups);
        return view('products.create', compact('fields'));
    }

    public function store(Request $request)
    {
        // TODO validate request
        $validatedData = $request->validate([
            'code'=>'required|unique:products,code',
            'name'=>'required|max:20',
            'group'=>'required',
            'location'=>'required',
            'quantity'=>'required',
            'quantity_warning'=>'required',
            'oversell'=>'nullable',
            'purchace_price'=>'required',
            'selling_price'=>'required',
            'discount_formula'=>'required',
            'description'=>'required'
        ]);

        $validatedData['oversell'] = isset($validatedData['oversell']) ? 1 : 0;

        Models\Product::create($validatedData);

        return redirect()->route('products.index')->with('success', 'Product created successfully.');
    }

    public function show($id)
    {
        // Read - Display a single item
    }

    public function edit(Models\Product $product)
    {
        $groups = Models\ProductGroup::select('id', 'name')->get();
        $fields = $this->fields($groups);
        return view('products.edit', compact('product', 'fields'));
    }

    public function update(Request $request, Models\Product $product)
    {
        // TODO validate request
        $validatedData = $request->validate([
            'code'=>'required|exists:products,code',
            'name'=>'required|max:20',
            'group'=>'required',
            'location'=>'required',
            'quantity'=>'required',
            'quantity_warning'=>'required',
            'oversell'=>'nullable',
            'purchace_price'=>'required',
            'selling_price'=>'required',
            'discount_formula'=>'required',
            'description'=>'required'
        ]);

        $validatedData['oversell'] = isset($validatedData['oversell']) ? 1 : 0;

        $product->update($validatedData);

        return redirect()->route('products.index')->with('success', 'Product updated successfully.');
    }

    public function destroy(Models\Product $product)
    {
        $product->delete();

        return redirect()->route('products.index')->with('success', 'Product deleted successfully.');
    }

    public function fields($groups): array
    {
        return [
            'code' => ['label' => 'کد', 'type' => 'text'],
            'name' => ['label' => 'نام محصول', 'type' => 'text'],
            'group' => ['label' => 'گروه محصول', 'type' => 'select', 'options' => $groups],
            'location' => ['label' => 'مکان', 'type' => 'text'],
            'quantity' => ['label' => 'تعداد', 'type' => 'number'],
            'quantity_warning' => ['label' => 'هشدار مقدار', 'type' => 'number'],
            'oversell' => ['label' => 'بیش فروش', 'type' => 'checkbox'],
            'purchace_price' => ['label' => 'قیمت خرید', 'type' => 'number'],
            'selling_price' => ['label' => 'قیمت فروش', 'type' => 'number'],
            'discount_formula' => ['label' => 'فرمول تخفیف', 'type' => 'text'],
            'description' => ['label' => 'توضیحات', 'type' => 'textarea'],
        ];
    }
}
