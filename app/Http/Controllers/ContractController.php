<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContractRequest;
use App\Models\Contract;
use Illuminate\Http\Request;

class ContractController extends Controller
{
    public function index()
    {
        $contracts = Contract::paginate(10);
        return view('contracts.index', compact('contracts'));
    }

    public function create()
    {
        return view('contracts.create');
    }

    public function store(ContractRequest $request)
    {
        Contract::create($request->all());
        return redirect()->route('payroll.contracts.index')->with('success', 'Contract created successfully.');
    }

    public function show($id)
    {
        $contract = Contract::findOrFail($id);
        return view('contracts.show', compact('contract'));
    }

    public function edit($id)
    {
        $contract = Contract::findOrFail($id);
        return view('contracts.edit', compact('contract'));
    }

    public function update(ContractRequest $request, $id)
    {
        $contract = Contract::findOrFail($id);
        $contract->update($request->all());
        return redirect()->route('payroll.contracts.index')->with('success', 'Contract updated successfully.');
    }

    public function destroy($id)
    {
        Contract::destroy($id);
        return redirect()->route('payroll.contracts.index')->with('success', 'Contract deleted successfully.');
    }
}
