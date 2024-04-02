<?php

namespace App\Http\Controllers;

use App\Models;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index()
    {
        $transactions = Models\Transaction::with('customer')->paginate(12);
        $cols = [
            'code', 'date', 'bill', 'customer', 'addition', 'subtraction', 'tax',
            'payable_amount', 'cash_payment', 'ship_date', 'destination',
            'ship_via', 'permanent', 'description', 'sell', 'activated'
        ];
        return view('transactions.index', compact('transactions', 'cols'));
    }

    public function create()
    {
        $customers = Models\Customer::select('id', 'name')->get();
        $fields = $this->fields($customers);
        return view('transactions.create', compact('fields'));
    }

    public function store(Request $request)
    {
        // TODO validate request
        $validatedData = $request->validate([
            'code' => 'required',
            'date' => 'required',
            'bill' => 'required',
            'customer_id' => 'required',
            'addition' => 'required',
            'subtraction' => 'required',
            'tax' => 'required',
            'payable_amount' => 'required',
            'cash_payment' => 'required',
            'destination' => 'required',
            'ship_date' => 'required',
            'ship_via' => 'required',
            'permanent' => 'nullable',
            'description' => 'required',
            'sell' => 'nullable',
            'activated' => 'nullable'
        ]);

        $validatedData['permanent'] = isset($validatedData['permanent']) ? 1 : 0;
        $validatedData['sell'] = isset($validatedData['sell']) ? 1 : 0;
        $validatedData['activated'] = isset($validatedData['activated']) ? 1 : 0;

        Models\Transaction::create($validatedData);

        return redirect()->route('transactions.index')->with('success', 'Transaction created successfully.');
    }

    public function show($id)
    {
        // Read - Display a single item
    }

    public function edit(Models\Transaction $transaction)
    {
        $customers = Models\Customer::select('id', 'name')->get();
        $fields = $this->fields($customers);
        return view('transactions.edit', compact('transaction', 'fields'));
    }

    public function update(Request $request, Models\Transaction $transaction)
    {
        // TODO validate request
        $validatedData = $request->validate([
            'code' => 'required',
            'date' => 'required',
            'bill' => 'required',
            'customer_id' => 'required',
            'addition' => 'required',
            'subtraction' => 'required',
            'tax' => 'required',
            'payable_amount' => 'required',
            'cash_payment' => 'required',
            'destination' => 'required',
            'ship_date' => 'required',
            'ship_via' => 'required',
            'permanent' => 'nullable',
            'description' => 'required',
            'sell' => 'nullable',
            'activated' => 'nullable'
        ]);

        $validatedData['permanent'] = isset($validatedData['permanent']) ? 1 : 0;
        $validatedData['sell'] = isset($validatedData['sell']) ? 1 : 0;
        $validatedData['activated'] = isset($validatedData['activated']) ? 1 : 0;

        $transaction->update($validatedData);

        return redirect()->route('transactions.index')->with('success', 'Transaction updated successfully.');
    }

    public function destroy(Models\Transaction $transaction)
    {
        $transaction->delete();

        return redirect()->route('transactions.index')->with('success', 'Transaction deleted successfully.');
    }

    public function fields($customers): array
    {
        return [
            'code' => ['label' => 'code', 'type' => 'text'],
            'date' => ['label' => 'date', 'type' => 'date'],
            'bill' => ['label' => 'bill', 'type' => 'number'],
            'customer_id' => ['label' => 'customer', 'type' => 'select', 'options' => $customers],
            'addition' => ['label' => 'addition', 'type' => 'number'],
            'subtraction' => ['label' => 'subtraction', 'type' => 'number'],
            'tax' => ['label' => 'tax', 'type' => 'number'],
            'payable_amount' => ['label' => 'payable_amount', 'type' => 'number'],
            'cash_payment' => ['label' => 'cash_payment', 'type' => 'number'],
            'destination' => ['label' => 'destination', 'type' => 'text'],
            'ship_date' => ['label' => 'ship_date', 'type' => 'date'],
            'ship_via' => ['label' => 'ship_via', 'type' => 'date'],
            'permanent' => ['label' => 'permanent', 'type' => 'checkbox'],
            'description' => ['label' => 'description', 'type' => 'textarea'],
            'sell' => ['label' => 'sell', 'type' => 'checkbox'],
            'activated' => ['label' => 'activated', 'type' => 'checkbox']
        ];
    }
}
