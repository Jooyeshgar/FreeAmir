<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Warehouse;
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
        return view('warehouses.transfer', [
            'warehouses' => Warehouse::get(),
            'products' => Product::limit(30)->get(),
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
