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

        return redirect()
            ->route('ancillary-costs.index')
            ->with('success', __('Ancillary Cost created successfully.'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(AncillaryCost $ancillaryCost)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, AncillaryCost $ancillaryCost)
    {
        //
    }

    public function destroy(AncillaryCost $ancillaryCost)
    {
        $ancillaryCost->delete();

        return redirect()
            ->route('ancillary-costs.index')
            ->with('success', __('ancillary costs deleted successfully.'));
    }
}
