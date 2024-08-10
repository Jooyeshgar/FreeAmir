<?php

namespace App\Services;

use App\Exceptions\DocumentServiceExcepion;
use App\Exceptions\DocumentServiceException;
use App\Models\Document;
use App\Models\Transaction;
use App\Models\User;
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
        // try {
        //     //code...
        // } catch (DocumentServiceException $e) {
        //     throw ValidationException::withMessages([$e->getMessage()]);
        // }

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

        $document = null;
        DB::transaction(function () use ($data, $transactions, &$document) {
            $document = Document::create($data);

            foreach ($transactions as $transactionData) {
                $this->createTransaction($document, $transactionData);
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
            'title' => 'string',
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
            'user_id' => 'required|integer',
            'desc' => 'string',
            'value' => 'required|decimal:0,2',
        ]);

        if ($validator->fails()) {
            dump($data);
            throw new \Exception($validator->errors()->first());
        }

        $transaction = new Transaction();
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
}
