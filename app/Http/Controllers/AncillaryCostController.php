<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAncillaryCostRequest;
use App\Models\AncillaryCost;
use App\Models\Invoice;
use App\Services\AncillaryCostService;
use Illuminate\Http\Request;

class AncillaryCostController extends Controller
{
    public function index(Request $request)
    {
        $ancillaryCosts = AncillaryCost::with('invoice')
            ->orderByDesc('date')
            ->paginate(12);

        $ancillaryCosts->appends($request->query());

        return view('ancillaryCosts.index', compact('ancillaryCosts'));
    }

    public function create()
    {
        $invoices = Invoice::select('id', 'number')
            ->where('invoice_type', 'buy')
            ->orderByDesc('date')
            ->get();
        $ancillaryCost = new AncillaryCost;
        $ancillaryCostItems = old('ancillaryCosts') ?? [];

        return view('ancillaryCosts.create', compact('invoices', 'ancillaryCost', 'ancillaryCostItems'));
    }

    public function store(StoreAncillaryCostRequest $request)
    {
        $validated = $request->validated();
        $validated['company_id'] = session('active-company-id');

        AncillaryCostService::createAncillaryCost(auth()->user(), $validated);

        return redirect()
            ->route('ancillary-costs.index')
            ->with('success', __('Ancillary Cost created successfully.'));
    }

    public function edit(AncillaryCost $ancillaryCost)
    {
        $invoices = Invoice::select('id', 'number')->where('invoice_type', 'buy')->orderByDesc('date')->get();

        // Load ancillary cost items for editing
        $ancillaryCostItems = $ancillaryCost->items->map(function ($item) {
            return [
                'product_id' => $item->product_id,
                'amount' => $item->amount,
            ];
        })->toArray();

        // Convert VAT amount to VAT percentage
        $ancillaryCost['vat'] = $ancillaryCost['vat'] != 0 ? ($ancillaryCost['amount'] - $ancillaryCost['vat']) / $ancillaryCost['vat'] : 0;

        return view('ancillaryCosts.edit', compact('ancillaryCost', 'invoices', 'ancillaryCostItems'));
    }

    public function update(StoreAncillaryCostRequest $request, AncillaryCost $ancillaryCost)
    {
        $validated = $request->validated();
        $validated['company_id'] = session('active-company-id');

        AncillaryCostService::updateAncillaryCost(auth()->user(), $ancillaryCost, $validated);

        return redirect()
            ->route('ancillary-costs.index')
            ->with('success', __('Ancillary Cost updated successfully.'));
    }

    public function destroy(AncillaryCost $ancillaryCost)
    {
        AncillaryCostService::deleteAncillaryCost($ancillaryCost->id);

        return redirect()
            ->route('ancillary-costs.index')
            ->with('success', __('Ancillary Cost deleted successfully.'));
    }

    public function getInvoiceProducts($invoice_id)
    {
        $invoice = Invoice::with('items.product')->findOrFail($invoice_id);
        $products = collect($invoice->items)->map(function ($item) {
            return [
                'id' => $item->product->id,
                'name' => $item->product->name,
            ];
        })->unique('id')->values();

        return response()->json(['products' => $products]);
    }
}
