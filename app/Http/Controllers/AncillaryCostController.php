<?php

namespace App\Http\Controllers;

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

    public function store(Request $request) {}

    /**
     * Display the specified resource.
     */
    public function show(AncillaryCost $ancillaryCost)
    {
        //
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
    }
}
