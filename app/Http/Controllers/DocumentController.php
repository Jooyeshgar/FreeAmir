<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Models;
use App\Models\Document;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DocumentController extends Controller
{
    public function index()
    {
        $documents = Document::orderBy('id', 'desc')->get();

        return view('transactions.index', compact('documents'));
    }

    public function create()
    {
        $subjects = Subject::orderBy('code', 'asc')->get();
        $transactions = [new Transaction];
        $document = new Document();
        $previousDocumentNumber = Document::orderBy('id', 'desc')->first()->number ?? 0;
        return view('transactions.create', compact('document', 'previousDocumentNumber', 'subjects', 'transactions'));
    }

    public function store(StoreTransactionRequest $request)
    {

        DB::beginTransaction();

        $document = Document::create([
            'title' => $request->title,
            'number' => $request->number,
            'date' => jalali_to_gregorian_date($request->date)
        ]);

        foreach ($request->input('transactions') as $transactionData) {
            $transactionData = (object) $transactionData;
            Transaction::create([
                'document_id' => $document->id,
                'user_id' => Auth::id(),
                'subject_id' => $transactionData->subject_id,
                'value' => $transactionData->credit ?: -1 * $transactionData->debit,
                'desc' => $transactionData->desc
            ]);
        }

        DB::commit();

        return redirect()->route('transactions.index')->with('success', 'Transactions created successfully.');
    }

    public function show($id)
    {
        // Read - Display a single item
    }

    public function edit($id)
    {

        $document = Models\Document::find($id);
        if ($document) {
            $transactions = $document->transaction;
            $subjects = Subject::all();
            $previousDocumentNumber = Document::orderBy('id', 'desc')->where('id', '<', $id)->first()->number ?? 0;
            return view('transactions.edit', compact('previousDocumentNumber', 'document', 'subjects', 'transactions'));
        } else {
            return redirect()->route('transactions.index')->with('error', 'Transaction not found.');
        }
    }

    public function update(StoreTransactionRequest $request, $id)
    {


        $document = Document::findOrFail($id);
        $ids = [];
        DB::beginTransaction();

        $document->update([
            'title' => $request->title,
            'number' => $request->number,
            'date' => jalali_to_gregorian_date($request->date)
        ]);

        foreach ($request->input('transactions') as $transactionData) {
            $transactionData = (object)$transactionData;
            $ids[] = $transactionData->transaction_id;
            $transaction = Transaction::where('id', $transactionData->transaction_id)->first();
            $payload = [
                'document_id' => $document->id,
                'user_id' => Auth::id(),
                'subject_id' => $transactionData->subject_id,
                'value' => $transactionData->credit ?: -1 * $transactionData->debit,
                'desc' => $transactionData->desc
            ];
            if ($transaction) {
                $transaction->update($payload);
            } else {
                Transaction::create($payload);
            }
        }
        Transaction::where('document_id', $document->id)->whereNotIn('id', $ids)->delete();


        DB::commit();

        return redirect()->route('transactions.index')->with('success', 'Transaction updated successfully.');
    }

    public function destroy(Models\Document $transaction)
    {
        $transaction->transaction()->delete();
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
            'activated' => ['label' => 'activated', 'type' => 'checkbox'],
        ];
    }
}
