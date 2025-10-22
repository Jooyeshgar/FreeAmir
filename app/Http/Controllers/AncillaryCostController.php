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
        $invoices = Invoice::select('id', 'number')->get();
        $ancillaryCosts = old('ancillaryCosts') ?? $this->preparedAncillaryCosts(collect([new AncillaryCost]));

        $total = count($ancillaryCosts);

        return view('ancillaryCosts.create', compact('invoices', 'ancillaryCosts', 'total'));
    }

    public function store(StoreAncillaryCostRequest $request)
    {
        AncillaryCost::create($request->validated());

        return redirect()
            ->route('ancillary-costs.index')
            ->with('success', __('Ancillary Cost created successfully.'));
    }

    public function edit(AncillaryCost $ancillaryCost)
    {
        $invoices = Invoice::select('id', 'number')->get();

        return view('ancillaryCosts.edit', compact('ancillaryCost', 'invoices'));
    }

    public function update(StoreAncillaryCostRequest $request, AncillaryCost $ancillaryCost)
    {
        $ancillaryCost->update($request->validated());

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
        return $ancillaryCosts->map(function ($ancillaryCost, $i) {
            return [
                'id' => $i + 1,
                'date' => $ancillaryCost->date,
                'product_id' => $ancillaryCost->product_id,
                'invoice_id' => $ancillaryCost->invoice_id,
                'description' => $ancillaryCost->description,
                'amount' => $ancillaryCost->amount,
            ];
        });
    }
}
