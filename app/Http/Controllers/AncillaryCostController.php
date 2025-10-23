<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAncillaryCostRequest;
use App\Models\AncillaryCost;
use App\Models\Invoice;
use Illuminate\Http\Request;

class AncillaryCostController extends Controller
{
    public function index(Request $request)
    {
        $ancillaryCosts = AncillaryCost::with('invoice')->paginate(12);
        $ancillaryCosts->appends($request->query());

        return view('ancillaryCosts.index', compact('ancillaryCosts'));
    }

    public function create()
    {
        $invoices = Invoice::select('id', 'number')->where('invoice_type', 'sell')->orWhere('invoice_type', 'return_sell')->get();
        $ancillaryCost = new AncillaryCost;
        $ancillaryCosts = old('ancillaryCosts') ?? $this->preparedAncillaryCosts(collect([new AncillaryCost]));

        return view('ancillaryCosts.create', compact('invoices', 'ancillaryCost', 'ancillaryCosts'));
    }

    public function store(StoreAncillaryCostRequest $request)
    {
        $validated = $request->validated();
        dd($validated);

        if (! empty($validated['ancillaryCosts'])) {
            foreach ($validated['ancillaryCosts'] as $costData) {
                AncillaryCost::create([
                    'invoice_id' => $validated['invoice_id'],
                    'date' => $validated['date'],
                    'product_id' => $costData['product_id'],
                    'description' => $costData['description'],
                    'amount' => $costData['amount'],
                ]);
            }
        }

        return redirect()
            ->route('ancillary-costs.index')
            ->with('success', __('Ancillary Cost created successfully.'));
    }

    public function edit(AncillaryCost $ancillaryCost)
    {
        $invoices = Invoice::select('id', 'number')->get();

        // Get all ancillary costs for the same invoice to allow editing
        $ancillaryCostsCollection = AncillaryCost::where('invoice_id', $ancillaryCost->invoice_id)->get();
        $ancillaryCosts = $this->preparedAncillaryCosts($ancillaryCostsCollection);

        return view('ancillaryCosts.edit', compact('ancillaryCost', 'invoices', 'ancillaryCosts'));
    }

    public function update(StoreAncillaryCostRequest $request, AncillaryCost $ancillaryCost)
    {
        $validated = $request->validated();

        // Delete all existing ancillary costs for this invoice
        AncillaryCost::where('invoice_id', $validated['invoice_id'])->delete();

        // Create new ones
        if (! empty($validated['ancillaryCosts'])) {
            foreach ($validated['ancillaryCosts'] as $costData) {
                AncillaryCost::create([
                    'invoice_id' => $validated['invoice_id'],
                    'date' => $validated['date'],
                    'product_id' => $costData['product_id'],
                    'description' => $costData['description'],
                    'amount' => $costData['amount'],
                ]);
            }
        }

        return redirect()
            ->route('ancillary-costs.index')
            ->with('success', __('Ancillary Cost updated successfully.'));
    }

    public function destroy(AncillaryCost $ancillaryCost)
    {
        $ancillaryCost->delete();

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

    private function preparedAncillaryCosts($ancillaryCosts)
    {
        return $ancillaryCosts->map(function ($ancillaryCost) {
            return [
                'product_id' => $ancillaryCost->product_id ?? '',
                'description' => $ancillaryCost->description?->value ?? '',
                'amount' => $ancillaryCost->amount ?? 0,
            ];
        })->toArray();
    }
}
