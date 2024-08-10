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
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;

class DocumentController extends Controller
{
    public function index()
    {
        $documents = Document::orderBy('id', 'desc')->paginate(10);
        return view('documents.index', compact('documents'));
    }

    public function create()
    {
        $subjects = Subject::orderBy('code', 'asc')->get();
        if (!old('transactions')) {
            $transactions = [new Transaction];
        } else {
            $transactions = [];
            foreach (old('transactions') as $item) {
                $transaction = new Transaction();
                $transaction->subject_id = $item['subject_id'];
                $transaction->desc = $item['desc'];
                $transaction->value = $item['debit'] ? -1 * $item['debit'] : $item['credit'];
                $transactions[] = $transaction;
            }
        }

        $document = new Document();
        $previousDocumentNumber = Document::orderBy('id', 'desc')->first()->number ?? 0;
        return view('documents.create', compact('document', 'previousDocumentNumber', 'subjects', 'transactions'));
    }

    public function store(StoreTransactionRequest $request)
    {

        DB::beginTransaction();
        $document = Document::create([
            'title' => $request->title,
            'number' => $request->number,
            'date' => jalali_to_gregorian_date($request->date),
            'user_id' => Auth::id()
        ]);

        foreach ($request->input('transactions') as $transactionData) {
            $transactionData = (object) $transactionData;
            Transaction::create([
                'document_id' => $document->id,
                'subject_id' => $transactionData->subject_id,
                'value' => $transactionData->credit ?: -1 * $transactionData->debit,
                'desc' => $transactionData->desc
            ]);
        }

        DB::commit();

        return redirect()->route('documents.index')->with('success', 'Transactions created successfully.');
    }

    public function show(Document $document)
    {
        return view('documents.show', compact('document'));
    }

    public function edit($id)
    {

        $document = Models\Document::find($id);
        if ($document) {
            if (!old('transactions')) {
                $transactions = $document->transactions;
            } else {
                $transactions = [];
                foreach (old('transactions') as $item) {
                    $transaction = new Transaction();
                    $transaction->subject_id = $item['subject_id'];
                    $transaction->desc = $item['desc'];
                    $transaction->value = $item['debit'] ? -1 * $item['debit'] : $item['credit'];
                    $transactions[] = $transaction;
                }
            }

            $subjects = Subject::all();
            $previousDocumentNumber = Document::orderBy('id', 'desc')->where('id', '<', $id)->first()->number ?? 0;
            return view('documents.edit', compact('previousDocumentNumber', 'document', 'subjects', 'transactions'));
        } else {
            return redirect()->route('documents.index')->with('error', 'Transaction not found.');
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
            'date' => jalali_to_gregorian_date($request->date, '/'),
            'user_id' => Auth::id()
        ]);

        foreach ($request->input('transactions') as $transactionData) {
            $transactionData = (object)$transactionData;
            $ids[] = $transactionData->transaction_id;
            $transaction = Transaction::where('id', $transactionData->transaction_id)->first();
            $payload = [
                'document_id' => $document->id,
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

        return redirect()->route('documents.index')->with('success', 'Transaction updated successfully.');
    }

    public function destroy(Models\Document $transaction)
    {
        $transaction->transaction()->delete();
        $transaction->delete();

        return redirect()->route('documents.index')->with('success', 'Transaction deleted successfully.');
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
