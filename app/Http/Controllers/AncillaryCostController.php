<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAncillaryCostRequest;
use App\Models\AncillaryCost;
use App\Models\Invoice;
use App\Services\AncillaryCostService;
use Exception;
use Illuminate\Http\Request;

class AncillaryCostController extends Controller
{
    public function index(Request $request)
    {
        $ancillaryCosts = AncillaryCost::with('invoice')->orderBy('date')->paginate(12);

        $ancillaryCosts->appends($request->query());

        return view('ancillaryCosts.index', compact('ancillaryCosts'));
    }

    public function create()
    {
        $invoices = AncillaryCostService::getAllowedInvoicesForAncillaryCostsCreatingOrEditing();
        $ancillaryCost = new AncillaryCost;
        $ancillaryCostItems = old('ancillaryCosts') ?? [];

        return view('ancillaryCosts.create', compact('invoices', 'ancillaryCost', 'ancillaryCostItems'));
    }

    public function store(StoreAncillaryCostRequest $request)
    {
        $validated = $request->validated();
        $validated['company_id'] = session('active-company-id');

        $validatedInvoicesId = AncillaryCostService::getAllowedInvoicesForAncillaryCostsCreatingOrEditing()->pluck('id')->toArray();
        if (! in_array($validated['invoice_id'], $validatedInvoicesId)) {
            throw new Exception(__('Ancillary Cost cannot be created.'), 400);
        }

        AncillaryCostService::createAncillaryCost(auth()->user(), $validated);

        return redirect()
            ->route('ancillary-costs.index')
            ->with('success', __('Ancillary Cost created successfully.'));
    }

    public function edit(AncillaryCost $ancillaryCost)
    {
        $invoices = AncillaryCostService::getAllowedInvoicesForAncillaryCostsCreatingOrEditing();

        // Load ancillary cost items for editing
        $ancillaryCostItems = $ancillaryCost->items->map(function ($item) {
            return [
                'product_id' => $item->product_id,
                'amount' => $item->amount,
            ];
        })->toArray();

        // Calculate VAT percentage: (vat_amount / subtotal_before_vat) * 100
        $subtotalBeforeVat = $ancillaryCost['amount'] - $ancillaryCost['vat'];
        $vatPercentage = $subtotalBeforeVat > 0 ? ($ancillaryCost['vat'] / $subtotalBeforeVat) * 100 : 0;
        $ancillaryCost['vat'] = $vatPercentage;

        return view('ancillaryCosts.edit', compact('ancillaryCost', 'invoices', 'ancillaryCostItems'));
    }

    public function update(StoreAncillaryCostRequest $request, AncillaryCost $ancillaryCost)
    {
        if (AncillaryCostService::getEditDeleteStatus($ancillaryCost)['allowed'] === false) {
            throw new Exception(__('Ancillary Cost cannot be edited.'), 400);
        }

        $validated = $request->validated();
        $validated['company_id'] = session('active-company-id');

        AncillaryCostService::updateAncillaryCost($ancillaryCost, $validated);

        return redirect()
            ->route('ancillary-costs.index')
            ->with('success', __('Ancillary Cost updated successfully.'));
    }

    public function destroy(AncillaryCost $ancillaryCost)
    {
        if (AncillaryCostService::getEditDeleteStatus($ancillaryCost)['allowed'] === false) {
            throw new Exception(__('Ancillary Cost cannot be deleted.'), 400);
        }

        AncillaryCostService::deleteAncillaryCost($ancillaryCost);

        return redirect()
            ->route('ancillary-costs.index')
            ->with('success', __('Ancillary Cost deleted successfully.'));
    }

    public function getBuyInvoiceProducts($invoice_id)
    {
        $invoice = Invoice::findOrFail($invoice_id);

        if (! $invoice) {
            return response()->json(['products' => []]);
        }

        $invoiceItems = $invoice->items()
            ->where('itemable_type', \App\Models\Product::class)
            ->with('itemable')
            ->get();

        $products = collect($invoiceItems)->map(function ($item) {
            return [
                'id' => $item->itemable->id,
                'name' => $item->itemable->name,
                'quantity' => (int) $item->quantity,
            ];
        })->unique('id')->values();

        return response()->json(['products' => $products]);
    }
}
