<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Warehouse;
use App\Models\WarehouseTransfer;
use App\Services\WarehouseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    public function __construct(private readonly WarehouseService $warehouseService) {}

    public function index(Request $request): View
    {
        $inventory = $request->input('Warehouse_inventory', $request->input('warehouse_inventory'));
        $warehouseCount = Warehouse::count();

        $query = Warehouse::query()->withSum('stocks', 'quantity')->withSum('stocks', 'average_cost')->withCount([
            'stocks as positive_stock_count' => fn ($query) => $query->where('quantity', '>', 0),
            'transfersFrom as outgoing_transfer_count',
            'transfersTo as incoming_transfer_count',
        ])
            ->when($request->filled('name'), fn ($query) => $query->where('name', 'like', '%'.$request->input('name').'%'))
            ->when($request->filled('code'), fn ($query) => $query->where('code', 'like', '%'.$request->input('code').'%'))
            ->when(is_numeric($inventory), function ($query) use ($inventory) {
                $query->whereRaw(
                    '(SELECT COALESCE(SUM(quantity), 0) FROM warehouse_product_stocks WHERE warehouse_product_stocks.warehouse_id = warehouses.id) = ?',
                    [(float) $inventory]
                );
            });

        $warehouses = $query->paginate(20)->withQueryString();

        return view('warehouses.index', compact('warehouses', 'warehouseCount'));
    }

    public function create()
    {
        return view('warehouses.create', ['warehouse' => new Warehouse]);
    }

    public function show(Warehouse $warehouse)
    {
        $warehouseCount = Warehouse::count();
        $stocks = $warehouse->stocks()->with('product.productGroup')->whereHas('product')->orderByDesc('quantity')->paginate(20);

        return view('warehouses.show', compact('warehouse', 'stocks', 'warehouseCount'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->warehouseService->create($this->validateWarehouse($request));

        return redirect()->route('warehouses.index')->with('success', __('Warehouse created successfully.'));
    }

    public function edit(Warehouse $warehouse)
    {
        return view('warehouses.edit', compact('warehouse'));
    }

    public function update(Request $request, Warehouse $warehouse): RedirectResponse
    {
        $this->warehouseService->update($warehouse, $this->validateWarehouse($request, $warehouse));

        return redirect()->route('warehouses.index')->with('success', __('Warehouse updated successfully.'));
    }

    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        if (Warehouse::count() <= 1) {
            return back()->with('error', __('At least one warehouse must remain.'));
        }
        if ($warehouse->stocks()->where('quantity', '>', 0)->exists()) {
            return back()->with('error', __('A warehouse with stock cannot be deleted.'));
        }
        if ($warehouse->transfersFrom()->exists() || $warehouse->transfersTo()->exists()) {
            return back()->with('error', __('A warehouse with transfer history cannot be deleted.'));
        }
        $warehouse->delete();

        return back()->with('success', __('Warehouse deleted successfully.'));
    }

    public function transferForm()
    {
        $selectedWarehouse = request()->filled('from_warehouse_id')
            ? Warehouse::find(request()->integer('from_warehouse_id'))
            : null;
        $selectedProduct = request()->filled('product_id')
            ? Product::with('productGroup')->find(request()->integer('product_id'))
            : null;

        $products = Product::with('productGroup')->orderBy('name')->limit(30)->get();
        if ($selectedProduct && ! $products->contains('id', $selectedProduct->id)) {
            $products->prepend($selectedProduct);
        }

        return view('warehouses.transfer', [
            'warehouses' => Warehouse::get(),
            'products' => $products,
            'selectedWarehouse' => $selectedWarehouse,
            'selectedProduct' => $selectedProduct,
        ]);
    }

    public function searchProducts(Request $request)
    {
        $validated = $request->validate(['q' => 'required|string|max:100']);
        $products = Product::with('productGroup')
            ->where('name', 'like', '%'.$validated['q'].'%')
            ->orderBy('name')
            ->limit(30)
            ->get();

        return response()->json($products->isEmpty() ? [] : [[
            'id' => 'group_products',
            'headerGroup' => 'product',
            'options' => $products->map(function (Product $product) {
                $group = $product->productGroup ?? (object) ['id' => 0, 'name' => 'General'];

                return [
                    'id' => $product->id,
                    'groupId' => $group->id,
                    'groupName' => $group->name,
                    'text' => $product->name,
                    'type' => 'product',
                ];
            })->groupBy('groupId'),
        ]]);
    }

    public function transferHistory(Request $request): View
    {
        $query = WarehouseTransfer::query()->with(['product', 'fromWarehouse', 'toWarehouse', 'transferor'])->latest('transferred_at')->latest('id');

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        if ($request->filled('from_warehouse_id')) {
            $query->where('from_warehouse_id', $request->input('from_warehouse_id'));
        }

        if ($request->filled('to_warehouse_id')) {
            $query->where('to_warehouse_id', $request->input('to_warehouse_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('transferred_at', '>=', jalaliInputToGregorian($request->input('date_from'), 'date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('transferred_at', '<=', jalaliInputToGregorian($request->input('date_to'), 'date_to'));
        }

        $transfers = $query->paginate(20)->withQueryString();

        return view('warehouses.transfer-history', [
            'transfers' => $transfers,
            'warehouses' => Warehouse::orderBy('name')->get(),
            'products' => Product::orderBy('name')->get(),
        ]);
    }

    public function transfer(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('company_id', getActiveCompany())],
            'from_warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('company_id', getActiveCompany())],
            'to_warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('company_id', getActiveCompany())],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);
        $this->warehouseService->transfer(Product::findOrFail($data['product_id']), Warehouse::findOrFail($data['from_warehouse_id']), Warehouse::findOrFail($data['to_warehouse_id']), (float) $data['quantity'], $data['description'] ?? null);

        return redirect()->route('warehouses.transfer')->with('success', __('Stock transferred successfully.'));
    }

    private function validateWarehouse(Request $request, ?Warehouse $warehouse = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('warehouses', 'name')->where('company_id', getActiveCompany())->ignore($warehouse)],
            'code' => ['nullable', 'string', 'max:30', Rule::unique('warehouses', 'code')->where('company_id', getActiveCompany())->ignore($warehouse)],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
