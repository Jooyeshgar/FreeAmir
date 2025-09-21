<?php

namespace App\Services;

use App\Exceptions\DocumentServiceException;
use App\Models\Document;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DocumentService
{
    /**
     * Create a new document.
     *
     * @param array $data
     * @param array $transactions
     * @return Document
     */
    public static function createDocument(User $user, array $data, array $transactions)
    {

        $validator = Validator::make($data, [
            'number' => 'nullable|integer',
            'title' => 'nullable|string',
            'date' => 'required|date',
        ]);

        if ($validator->fails()) {
            throw new DocumentServiceException($validator->errors()->first());
        }

        $data['creator_id'] = $user->id;
        if (empty($data['number'])) {
            $data['number'] = Document::max('number') + 1;
        }

        $data['company_id'] = session('active-company-id');

        $document = null;
        DB::transaction(function () use ($data, $transactions, &$document) {

            $document = Document::create($data);

            foreach ($transactions as $transactionData) {
                DocumentService::createTransaction($document, $transactionData);
            }
        });

        return $document;
    }

    /**
     * Update an existing document.
     *
     * @param Document $document
     * @param array $data
     * @return Document
     */
    public static function updateDocument(Document $document, array $data)
    {
        $validator = Validator::make($data, [
            'number' => 'integer',
            'title' => 'nullable|string|min:3|max:255',
            'date' => 'date',
        ]);

        if ($validator->fails()) {
            throw new \Exception($validator->errors()->first());
        }

        $document->fill($data);
        $document->save();

        return $document;
    }

    /**
     * Approve a document.
     *
     * @param Document $document
     * @return bool
     */
    public static function approveDocument(Document $document)
    {
        $sum = $document->transactions()->sum('value');

        if ($sum !== 0) {
            throw new \Exception('The sum of transactions must be zero');
        }

        $document->approved_at = now();
        $document->save();

        return true;
    }

    /**
     * Create a new transaction for a document.
     *
     * @param Document $document
     * @param array $data
     * @return Transaction
     */
    public static function createTransaction(Document $document, array $data)
    {
        $validator = Validator::make($data, [
            'subject_id' => 'required|integer',
            'desc' => 'string',
            'value' => 'required|decimal:0,2',
            'created_at' => 'nullable|date',
            'updated_at' => 'nullable|date',
        ]);
        if ($validator->fails()) {
            throw new \Exception($validator->errors()->first());
        }

        $transaction = new Transaction();
        $data['user_id'] ??= Auth::id();
        $transaction->fill($data);
        $transaction->document_id = $document->id;
        $transaction->save();

        return $transaction;
    }

    /**
     * Update an existing transaction.
     *
     * @param Transaction $transaction
     * @param array $data
     * @return Transaction
     */
    public static function updateTransaction(Transaction $transaction, array $data)
    {
        $validator = Validator::make($data, [
            'subject_id' => 'integer',
            'user_id' => 'integer',
            'desc' => 'string',
            'value' => 'integer',
        ]);

        if ($validator->fails()) {
            throw new \Exception($validator->errors()->first());
        }

        $transaction->fill($data);
        $transaction->save();

        return $transaction;
    }

    public static function updateDocumentTransactions(int $documentId, array $transactionsData): void
    {
        DB::beginTransaction();

        $existingTransactionIds = [];
        foreach ($transactionsData as $transactionData) {
            $transaction = Transaction::updateOrCreate(
                ['id' => $transactionData['transaction_id']],
                [
                    'document_id' => $documentId,
                    'subject_id' => $transactionData['subject_id'],
                    'desc' => $transactionData['desc'],
                    'value' => floatval($transactionData['credit']) - floatval($transactionData['debit']),
                ]
            );
            $existingTransactionIds[] = $transaction->id;
        }
        Transaction::where('document_id', $documentId)
            ->whereNotIn('id', $existingTransactionIds)
            ->delete();

        DB::commit();
    }

    public static function deleteDocument(int $documentId): void
    {
        DB::beginTransaction();
        Transaction::where('document_id', $documentId)->delete();
        Document::where('id', $documentId)->delete();
        DB::commit();
    }
}
