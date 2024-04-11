<?php

namespace App\Http\Controllers;

use App\Models;
use App\Models\Document;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;


class TransactionController extends Controller
{
    public function index()
    {
        $transactions = Transaction::all();
        $cols = ['ID', 'Subject ID', 'Document ID', 'User ID', 'Description', 'Value'];

        return view('transactions.index', compact('transactions', 'cols'));
    }


    public function create()
    {
        $users = User::all();
        $subjects = Subject::all();
        $transaction = new Transaction;

        return view('transactions.create', compact('users', 'subjects','transaction'));
    }


    public function store(Request $request)
    {
        $document = Document::create();

        foreach ($request->input('transactions') as $transactionData) {
            $validatedData = Validator::make($transactionData, [
                'subject_id' => 'required|exists:subjects,id',
                'user_id' => 'required|exists:users,id',
                'value' => 'required|integer',
                'desc' => 'required|string',
            ])->validate();

            Transaction::create($validatedData + ['document_id' => $document->id]);
        }

        return redirect()->route('transactions.index')->with('success', 'Transactions created successfully.');
    }






    public function show($id)
    {
        // Read - Display a single item
    }

    public function edit($id)
    {

        $transaction = Models\Transaction::find($id);

        if ($transaction) {
            $users = User::all();
            $subjects = Subject::all();

            return view('transactions.edit', compact('users', 'subjects', 'transaction'));
        } else {
            return redirect()->route('transactions.index')->with('error', 'Transaction not found.');
        }
    }



    public function update(Request $request, $id)
    {
        $validatedData = $request->validate([
            'transactions.0.subject_id' => 'exists:subjects,id',
            'transactions.0.user_id' => 'exists:users,id',
            'transactions.0.value' => 'integer',
            'transactions.0.desc' => 'string',
        ]);

        $transaction = Transaction::findOrFail($id);

        $transactionData = $validatedData['transactions'][0];

        $transaction->update($transactionData);

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
