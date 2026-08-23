<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Warehouse;
use App\Services\ProductImportService;
use App\Services\ProductService;
use App\Services\ReportExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
    ) {}

    public function report(Request $request, ReportExportService $reportExportService)
    {
        return $reportExportService->inlineResponse('warehouse_pdf', $request->all());
    }

    public function index()
    {
        $query = Product::with('warehouse')->orderBy('code');

        if (request()->has('name') && request('name')) {
            $query->where('name', 'like', '%'.request('name').'%');
        }

        if (request()->has('code') && request('code')) {
            $query->where('code', 'like', '%'.request('code').'%');
        }

        if (request()->has('group_name') && request('group_name')) {
            $searchGroupName = request('group_name');
            $query->whereHas('productGroup', function ($groupName) use ($searchGroupName) {
                $groupName->where('name', 'like', '%'.$searchGroupName.'%');
            });
        }

        if (request()->filled('min_quantity') && is_numeric(request('min_quantity'))) {
            $query->where('quantity', '>=', (float) request('min_quantity'));
        }

        if (request()->boolean('need_order')) {
            $query->where('quantity_warning', '>', 0)->whereColumn('quantity', '<=', 'quantity_warning');
        }

        $products = $query->paginate(12)->withQueryString();

        $products->transform(function ($product) {
            $product->needs_order = (float) ($product->quantity_warning ?? 0) > 0 && (float) $product->quantity <= (float) $product->quantity_warning;
            $product->unapprovedQuantity = $this->productService->unapprovedQuantity($product);
            $product->totalSellCount = $this->productService->totalSellCount($product);
            if (auth()->user()->can('reports.journal')) {
                $product->totalSell = $this->productService->totalSell($product);
                $product->salesProfit = $product->totalSell + $this->productService->totalCOGS($product);
            }

            return $product;
        });

        return view('products.index', [
            'products' => $products,
            'csvColumns' => $this->exportColumnMapping(),
            'reportColumns' => $this->reportColumnMapping(),
        ]);
    }

    public function create()
    {
        $groups = ProductGroup::select('id', 'name')->limit(20)->get();
        $warehouses = Warehouse::get();

        return view('products.create', compact('groups', 'warehouses'));
    }

    public function store(StoreProductRequest $request)
    {
        $validatedData = $request->getValidatedData();

        $product = $this->productService->create($validatedData);

        return redirect()->route('products.index')->with('success', __('Product created successfully.'));
    }

    public function edit(Product $product)
    {
        $productGroupIdsForSelect = ProductGroup::select('id', 'name')->limit(20)->pluck('id');
        $oldGroup = $product->productGroup;
        $groups = ProductGroup::whereIn('id', $productGroupIdsForSelect->push($oldGroup->id)->unique())->get();
        $warehouses = Warehouse::get();

        return view('products.edit', compact('product', 'groups', 'warehouses'));
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $validatedData = $request->getValidatedData();

        $this->productService->update($product, $validatedData);

        return redirect()->route('products.index')->with('success', __('Product updated successfully.'));
    }

    public function show(Product $product)
    {
        $product->load('productGroup', 'productWebsites', 'warehouse', 'warehouseStocks.warehouse');

        $product->lastCOG = $this->productService->lastApprovedBuyInvoiceItemCOG($product) ?? 0;
        $product->salesProfit = $this->productService->totalSell($product) + $this->productService->totalCOGS($product);

        $historyItems = $product->invoiceItems()
            ->with(['invoice.customer', 'invoice.ancillaryCosts.items' => function ($query) use ($product) {
                $query->where('product_id', $product->id);
            }])
            ->tap(function ($q) {
                foreach (['date', 'invoice_type', 'number'] as $col) {
                    $q->orderByDesc(
                        Invoice::select($col)->whereColumn('invoices.id', 'invoice_items.invoice_id')
                    );
                }
            })
            ->paginate(20);

        return view('products.show', compact('product', 'historyItems'));
    }

    public function destroy(Product $product)
    {
        $this->productService->delete($product);

        return redirect()->route('products.index')->with('success', __('Product deleted successfully.'));
    }

    public function export(Request $request, ReportExportService $reportExportService): StreamedResponse
    {
        return $reportExportService->downloadResponse('products_csv', $request->all());
    }

    public function importForm(): View
    {
        return view('products.import');
    }

    public function import(Request $request, ProductImportService $importService): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $result = $importService->import($request->file('file'), getActiveCompany());

        return redirect()->route('products.index')->with('success', __('Import complete: :imported products imported, :updated updated, :groups groups created.', [
            'imported' => $result['imported'],
            'updated' => $result['updated'],
            'groups' => $result['groups_created'],
        ]));
    }

    public function searchProductGroup(Request $request)
    {
        $validated = $request->validate([
            'q' => 'required|string|max:100',
        ]);

        $q = $validated['q'];
        $productGroups = ProductGroup::where('name', 'like', "%{$q}%")->select('id', 'name')->limit(20)->get();

        if ($productGroups->isEmpty()) {
            return response()->json([]);
        }

        $grouped = [
            0 => $productGroups->map(fn ($pg) => [
                'id' => $pg->id,
                'groupId' => 0,
                'groupName' => 'General',
                'text' => $pg->name,
                'type' => 'product group',
                'raw_data' => $pg->toArray(),
            ])->values()->all(),
        ];

        return response()->json([
            [
                'id' => 'group_product_groups',
                'headerGroup' => 'product group',
                'options' => (object) $grouped,
            ],
        ]);
    }

    private function exportColumnMapping(): array
    {
        return [
            'name' => __('Product name'),
            ...$this->reportColumnMapping(),
            'income_subject_code' => __('Revenue subject code'),
            'cogs_subject_code' => __('COGS subject code'),
            'inventory_subject_code' => __('Inventory subject code'),
            'sales_returns_subject_code' => __('Sales returns subject code'),
            'sstid' => __('Product SSTID'),
            'location' => __('Location in warehouse'),
            'quantity_warning' => __('Quantity warning'),
            'oversell' => __('Oversell'),
            'discount_formula' => __('Discount formula'),
            'description' => __('Description'),
            'vat' => __('VAT'),
        ];
    }

    private function reportColumnMapping(): array
    {
        return [
            'inbound' => __('Inbound'),
            'outbound' => __('Outbound'),
            'stock' => __('Stock'),
            'category' => __('Category'),
            'code' => __('Product code'),
            'selling_price' => __('Sale price'),
            'cost_of_goods' => __('Cost of goods'),
            'last_item_cost' => __('Last item cost'),
            'sales_profit' => __('Sales profit'),
            'revenue_account' => __('Revenue account amount'),
            'cogs_account' => __('COGS account amount'),
            'inventory_account' => __('Inventory account amount'),
            'sales_return_account' => __('Sales return account amount'),
        ];
    }
}
