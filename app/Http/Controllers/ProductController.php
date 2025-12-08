<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Services\ProductService;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
    ) {}

    public function index()
    {
        $query = Product::orderBy('code');

        if (request()->has('name') && request('name')) {
            $query->where('name', 'like', '%'.request('name').'%');
        }

        if (request()->has('group_name') && request('group_name')) {
            $searchGroupName = request('group_name');
            $query->whereHas('productGroup', function ($groupName) use ($searchGroupName) {
                $groupName->where('name', 'like', '%'.$searchGroupName.'%');
            });
        }

        $products = $query->paginate(12);

        $products->transform(function ($product) {
            $product->unapprovedQuantity = $this->productService->unapprovedQuantity($product);

            return $product;
        });

        return view('products.index', compact('products'));
    }

    public function create()
    {
        $groups = ProductGroup::select('id', 'name')->get();

        return view('products.create', compact('groups'));
    }

    public function store(StoreProductRequest $request)
    {
        $validatedData = $request->getValidatedData();

        $product = $this->productService->create($validatedData);

        return redirect()->route('products.index')->with('success', __('Product created successfully.'));
    }

    public function edit(Product $product)
    {
        $groups = ProductGroup::select('id', 'name', 'sstid')->get();

        return view('products.edit', compact('product', 'groups'));
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $validatedData = $request->getValidatedData();

        $this->productService->update($product, $validatedData);

        return redirect()->route('products.index')->with('success', __('Product updated successfully.'));
    }

    public function show(Product $product)
    {
        $product->load('productgroup', 'productWebsites');

        $product->lastCOG = $this->productService->lastApprovedBuyInvoiceItemCOG($product) ?? 0;
        $product->salesProfit = $this->productService->salesProfit($product) ?? 0;

        $historyItems = $product->invoiceItems()
            ->with('invoice')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->orderByDesc('invoices.date')
            ->select('invoice_items.*')
            ->paginate(10);

        $remainingBeforePage = $this->calculateRemainingBeforePage(
            $product->invoiceItems()
                ->with('invoice')
                ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
                ->orderByDesc('invoices.date')
                ->select('invoice_items.*'),
            $historyItems,
            $product->quantity
        );

        $this->addRunningRemaining($historyItems, $remainingBeforePage);

        return view('products.show', compact('product', 'historyItems'));
    }

    public function destroy(Product $product)
    {
        $this->productService->delete($product);

        return redirect()->route('products.index')->with('success', __('Product deleted successfully.'));
    }

    private function calculateRemainingBeforePage($query, $invoiceItems, int $startingQuantity): int
    {
        if ($invoiceItems->currentPage() <= 1 || $invoiceItems->isEmpty()) {
            return $startingQuantity;
        }

        $itemsBeforeCurrentPage = ($invoiceItems->currentPage() - 1) * $invoiceItems->perPage();

        $previousItems = $query->offset(0)
            ->limit($itemsBeforeCurrentPage)
            ->get();

        $remaining = $startingQuantity;

        foreach ($previousItems as $item) {
            if ($item->invoice->status->isApproved()) {
                if ($item->invoice->invoice_type === \App\Enums\InvoiceType::SELL) {
                    $remaining += $item->quantity;
                } elseif ($item->invoice->invoice_type === \App\Enums\InvoiceType::BUY) {
                    $remaining -= $item->quantity;
                }
            }
        }

        return $remaining;
    }

    private function addRunningRemaining($invoiceItems, int $remainingBeforePage): void
    {
        $remaining = $remainingBeforePage;

        foreach ($invoiceItems as $item) {
            if ($item->invoice->status->isApproved()) {
                if ($item->invoice->invoice_type === \App\Enums\InvoiceType::SELL) {
                    $remaining += $item->quantity;
                } elseif ($item->invoice->invoice_type === \App\Enums\InvoiceType::BUY) {
                    $remaining -= $item->quantity;
                }
            }

            $item->remaining = $remaining;
        }
    }
}
