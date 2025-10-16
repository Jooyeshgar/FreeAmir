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

        return view('ancillaryCosts.create', compact('invoices'));
    }

    public function store(StoreAncillaryCostRequest $request)
    {
        AncillaryCost::create($request->validated());
        $total_invoice_amount = Invoice::find($request->invoice_id)->amount;
        dd($total_invoice_amount);

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
}
