<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionRequest;
use App\Models\Document;
use App\Models\Subject;
use App\Models\Transaction;
use App\Services\DocumentService;
use Illuminate\Support\Facades\Auth;

class DocumentController extends Controller
{
    public function __construct() {}

    public function index()
    {
        $query = Document::orderBy('id', 'desc');

        if (request()->has('number') && request('number')) {
            $query->where('number', convertToFloat(request('number')));
        }

        if (request()->has('date') && request('date')) {
            $query->where('date', convertToGregorian(request('date')));
        }

        // Search by document title or transaction description
        if (request()->has('text') && request('text')) {
            $searchText = request('text');
            $query->where(function ($q) use ($searchText) {
                $q->where('title', 'like', '%'.$searchText.'%')
                    ->orWhereHas('transactions', function ($subQ) use ($searchText) {
                        $subQ->where('desc', 'like', '%'.$searchText.'%');
                    });
            });
        }

        $documents = $query->paginate(10);

        return view('documents.index', compact('documents'));
    }

    public function create()
    {
        $subjects = Subject::Some(orderBy: 'code', options: ['parent_id' => null])->with('children')->get();

        $transactions = old('transactions')
                    ? self::prepareTransactions(old('transactions'))
                    : self::prepareTransactions([new Transaction]);

        $total = count($transactions);
        $document = new Document;
        $previousDocumentNumber = floor(Document::orderBy('id', 'desc')->first()?->number) ?? 0;

        return view('documents.create', compact('document', 'previousDocumentNumber', 'subjects', 'transactions', 'total'));
    }

    public function store(StoreTransactionRequest $request)
    {
        $transactions = [];
        foreach ($request->input('transactions') as $transactionData) {
            $transactionData = (object) $transactionData;
            $transactions[] = [
                'subject_id' => $transactionData->subject_id,
                'value' => $transactionData->credit - $transactionData->debit,
                'desc' => $transactionData->desc,
            ];
        }

        DocumentService::createDocument(
            Auth::user(),
            [
                'title' => $request->title,
                'number' => $request->number,
                'date' => $request->date,
                'user_id' => Auth::id(),
            ],
            $transactions
        );

        return redirect()->route('documents.index')->with('success', __('Document created successfully.'));
    }

    public function show(Document $document)
    {
        return view('documents.show', compact('document'));
    }

    public function edit($id)
    {
        $document = Document::find($id);
        if ($document) {
            if ($document->documentable) {
                return redirect()->route('documents.index')->with('error', __('Cannot edit this document because it is linked to').' '.__(class_basename($document->documentable_type)).'.');
            }

            $transactions = old('transactions')
                    ? self::prepareTransactions(old('transactions'))
                    : self::prepareTransactions($document->transactions);

            $total = -1;

            $subjectsId = $document->transactions->pluck('subject_id')->unique()->toArray();
            $subjects = Subject::Some(orderBy: 'code', options: ['id' => $subjectsId])->with('children')->get();

            $previousDocumentNumber = Document::orderBy('id', 'desc')->where('id', '<', $id)->first()->number ?? 0;

            return view('documents.edit', compact('previousDocumentNumber', 'document', 'subjects', 'transactions', 'total'));
        } else {
            return redirect()->route('documents.index')->with('error', 'Document not found.');
        }
    }

    /**
     * Update the specified document and its transactions.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(StoreTransactionRequest $request, $id)
    {
        $document = Document::findOrFail($id);

        DocumentService::updateDocument($document, $request->toArray());

        DocumentService::updateDocumentTransactions($document->id, $request->input('transactions'));

        return redirect()->route('documents.index')->with('success', __('Document updated successfully.'));
    }

    public function destroy(int $documentId)
    {
        DocumentService::deleteDocument($documentId);

        return redirect()->route('documents.index')->with('success', __('Document deleted successfully.'));
    }

    /**
     * Duplicate the specified document with all its transactions.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function duplicate($id)
    {
        $originalDocument = Document::with('transactions')->findOrFail($id);

        // Get the next document number
        $nextDocumentNumber = Document::orderBy('id', 'desc')->first()->number + 1;

        // Prepare transactions data
        $transactions = [];
        foreach ($originalDocument->transactions as $transaction) {
            $transactions[] = [
                'subject_id' => $transaction->subject_id,
                'value' => $transaction->value,
                'desc' => $transaction->desc,
            ];
        }

        // Create the duplicated document
        $newDocument = DocumentService::createDocument(
            Auth::user(),
            [
                'title' => $originalDocument->title.' ('.__('Copy').')',
                'number' => $nextDocumentNumber,
                'date' => $originalDocument->date,
                'user_id' => Auth::id(),
            ],
            $transactions
        );

        return redirect()->route('documents.edit', $newDocument->id)
            ->with('success', __('Document duplicated successfully.'));
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

    /**
     * Prepares a collection of transaction data for further display.
     *
     * @param  array|\Illuminate\Support\Collection  $transactions
     * @return \Illuminate\Support\Collection
     */
    private static function prepareTransactions($transactions)
    {
        $transactions = collect($transactions);

        return $transactions->map(function ($t, $i) {
            $isModel = is_object($t);

            return [
                'id' => $i + 1,
                'transaction_id' => $isModel ? $t->id : ($t['transaction_id'] ?? null),
                'subject_id' => $isModel ? $t->subject_id : ($t['subject_id'] ?? ''),
                'subject' => $isModel ? ($t->subject?->name ?? '') : ($t['subject'] ?? ''),
                'code' => $isModel ? formatCode($t->subject?->code ?? '') : formatCode($t['code'] ?? ''),
                'desc' => $isModel ? ($t->desc ?? '') : ($t['desc'] ?? ''),
                'credit' => $isModel ? ($t->credit ?? 0) : ($t['credit'] ?? 0),
                'debit' => $isModel ? ($t->debit ?? 0) : ($t['debit'] ?? 0),
            ];
        });
    }
}
