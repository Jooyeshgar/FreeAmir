<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaxSlabRequest;
use App\Http\Requests\UpdateTaxSlabRequest;
use App\Models\TaxSlab;

class TaxSlabController extends Controller
{
    public function index()
    {
        $year = request('year');

        $query = TaxSlab::orderBy('year', 'desc')->orderBy('slab_order');

        if ($year) {
            $query->where('year', $year);
        }

        $taxSlabs = $query->paginate(15);

        return view('tax-slabs.index', compact('taxSlabs', 'year'));
    }

    public function create()
    {
        return view('tax-slabs.create');
    }

    public function store(StoreTaxSlabRequest $request)
    {
        TaxSlab::create(array_merge(
            $request->validated(),
            ['company_id' => getActiveCompany()]
        ));

        return redirect()->route('tax-slabs.index')->with('success', __('Tax slab created successfully.'));
    }

    public function show(TaxSlab $taxSlab)
    {
        return view('tax-slabs.show', compact('taxSlab'));
    }

    public function edit(TaxSlab $taxSlab)
    {
        return view('tax-slabs.edit', compact('taxSlab'));
    }

    public function update(UpdateTaxSlabRequest $request, TaxSlab $taxSlab)
    {
        $taxSlab->update($request->validated());

        return redirect()->route('tax-slabs.index')->with('success', __('Tax slab updated successfully.'));
    }

    public function destroy(TaxSlab $taxSlab)
    {
        $taxSlab->delete();

        return redirect()->route('tax-slabs.index')->with('success', __('Tax slab deleted successfully.'));
    }
}
