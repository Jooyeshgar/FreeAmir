<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Services\ProductService;
use App\Services\WarehouseDashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use PDF;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
    ) {}

    public function report(Request $request, WarehouseDashboardService $reportService)
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string'],
            'group_name' => ['nullable', 'string'],
            'min_quantity' => ['nullable', 'numeric'],
            'cols_submitted' => ['nullable'],
            'columns' => ['nullable', 'array'],
            'columns.*' => ['string'],
        ]);

        $data = $reportService->report($validated);
        $layout = $this->reportColumnLayout($data['columns']);
        $data = array_merge($data, $layout);
        $totals = $this->reportTotals($data['rows'], $layout['numeric']);
        $data['totalRow'] = $this->reportTotalRow($layout['visible'], $totals, $layout['addDesc']);

        $config = [
            'format' => 'A4',
            'orientation' => $data['portrait'] ? 'P' : 'L',
            'directionality' => 'rtl',
            'margin_top' => 28,
            'margin_bottom' => 18,
            'margin_header' => 6,
            'margin_footer' => 6,
            'defaultPageNumStyle' => 'persian',
        ];

        return PDF::loadView('warehouse.report-pdf', $data, [], $config)->stream('warehouse-report.pdf');
    }

    private function reportColumnLayout(array $columns): array
    {
        $order = [
            'name', 'code', 'category', 'inbound', 'outbound', 'stock',
            'selling_price', 'cost_of_goods', 'last_item_cost', 'sales_profit',
            'revenue_account', 'cogs_account', 'inventory_account', 'sales_return_account',
        ];
        $fixed = ['name', 'inbound', 'outbound', 'stock'];
        $numeric = [
            'inbound', 'outbound', 'stock', 'selling_price', 'cost_of_goods',
            'last_item_cost', 'sales_profit', 'revenue_account', 'cogs_account',
            'inventory_account', 'sales_return_account',
        ];

        $visible = array_values(array_filter(
            $order,
            fn ($c) => in_array($c, $fixed, true) || in_array($c, $columns, true),
        ));

        $count = count($visible);

        return [
            'visible' => $visible,
            'numeric' => $numeric,
            'addDesc' => $count < 9,
            'portrait' => $count < 6,
        ];
    }

    private function reportTotals(Collection $rows, array $numeric): array
    {
        $perUnit = ['selling_price', 'cost_of_goods', 'last_item_cost'];
        $totals = [];

        foreach ($numeric as $col) {
            if (in_array($col, $perUnit, true)) {
                continue;
            }

            $totals[$col] = (float) $rows->sum($col);
        }

        return $totals;
    }

    private function reportTotalRow(array $visible, array $totals, bool $addDesc): array
    {
        $slots = array_merge(['index'], $visible, $addDesc ? ['desc'] : []);

        $segments = [];
        $emptyRun = 0;
        $labelUsed = false;

        $flush = function () use (&$segments, &$emptyRun, &$labelUsed) {
            if ($emptyRun === 0) {
                return;
            }

            $segments[] = [
                'type' => 'merge',
                'colspan' => $emptyRun,
                'label' => $labelUsed ? '' : __('Total'),
            ];
            $labelUsed = true;
            $emptyRun = 0;
        };

        foreach ($slots as $slot) {
            if (array_key_exists($slot, $totals)) {
                $flush();
                $segments[] = ['type' => 'value', 'col' => $slot, 'value' => $totals[$slot]];
            } else {
                $emptyRun++;
            }
        }

        $flush();

        return $segments;
    }

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

        if (request()->filled('min_quantity') && is_numeric(request('min_quantity'))) {
            $query->where('quantity', '>=', (float) request('min_quantity'));
        }

        $products = $query->paginate(12)->withQueryString();

        $products->transform(function ($product) {
            $product->unapprovedQuantity = $this->productService->unapprovedQuantity($product);
            $product->totalSellCount = $this->productService->totalSellCount($product);
            if (auth()->user()->can('reports.journal')) {
                $product->totalSell = $this->productService->totalSell($product);
                $product->salesProfit = $product->totalSell + $this->productService->totalCOGS($product);
            }

            return $product;
        });

        return view('products.index', compact('products'));
    }

    public function create()
    {
        $groups = ProductGroup::select('id', 'name')->limit(20)->get();

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
        $productGroupIdsForSelect = ProductGroup::select('id', 'name')->limit(20)->pluck('id');
        $oldGroup = $product->productGroup;
        $groups = ProductGroup::whereIn('id', $productGroupIdsForSelect->push($oldGroup->id)->unique())->get();

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
        $product->load('productGroup', 'productWebsites');

        $product->lastCOG = $this->productService->lastApprovedBuyInvoiceItemCOG($product) ?? 0;
        $product->salesProfit = $this->productService->totalSell($product) + $this->productService->totalCOGS($product);

        $historyItems = $product->invoiceItems()
            ->with(['invoice.ancillaryCosts.items' => function ($query) use ($product) {
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
}
