<?php

namespace App\Services;

use App\Exceptions\DocumentServiceException;
use App\Models\Document;
use App\Models\DocumentFile;
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
     * @return Document
     */
    public static function createDocument(User $user, array $data, array $transactions)
    {

        $validator = Validator::make($data, [
            'number' => 'nullable|decimal:0,2',
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

        $data['company_id'] = isset($data['company_id']) ? $data['company_id'] : getActiveCompany();

        $document = null;
        DB::transaction(function () use ($data, $transactions, $user, &$document) {

            $document = Document::create($data);
            self::syncDocumentable($document, $data['documentable'] ?? null);
            if (! empty($data['approved_at']) && ! empty($data['approver_id'])) {
                self::approveDocument($user, $document);
            }
            foreach ($transactions as $transactionData) {
                DocumentService::createTransaction($document, $transactionData);
            }
        });

        return $document;
    }

    private static function approveDocument(User $user, Document $document): void
    {
        $sum = (float) $document->transactions()->sum('value');

        if ($sum !== 0.0) {
            throw ValidationException::withMessages([
                'transactions' => ['The sum of transactions must be zero'],
            ]);
        }

        $document->approved_at = now();
        $document->approver_id = $user->id;
        $document->save();
    }

    private static function unapproveDocument(User $user, Document $document): void
    {
        $document->approved_at = null;
        $document->approver_id = $user->id;
        $document->save();
    }

    /**
     * Associate a document with a documentable entity.
     */
    public static function syncDocumentable(Document $document, $documentable = null): void
    {
        $document->documentable()->associate($documentable);
        if ($document->isDirty(['documentable_id', 'documentable_type'])) {
            $document->save();
        }
    }

    /**
     * Update an existing document.
     *
     * @return Document
     */
    public static function updateDocument(Document $document, array $data)
    {
        $validator = Validator::make($data, [
            'number' => 'nullable|decimal:0,2',
            'title' => 'nullable|string|min:3|max:255',
            'date' => 'date',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $document->fill($data);

        self::syncDocumentable($document, $data['documentable'] ?? null);

        $document->save();

        return $document;
    }

    /**
     * Create a new transaction for a document.
     *
     * @return Transaction
     */
    public static function createTransaction(Document $document, array $data)
    {
        $validator = Validator::make($data, [
            'subject_id' => 'required|integer',
            'desc' => 'nullable|string',
            'value' => 'required|decimal:0,2',
            'created_at' => 'nullable|date',
            'updated_at' => 'nullable|date',
        ]);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $transaction = new Transaction;
        $data['user_id'] ??= Auth::id();
        $transaction->fill($data);
        $transaction->document_id = $document->id;
        $transaction->save();

        return $transaction;
    }

    /**
     * Update an existing transaction.
     *
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
            throw new ValidationException($validator);
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
                ['id' => $transactionData['transaction_id'] ?? null],
                [
                    'document_id' => $documentId,
                    'subject_id' => $transactionData['subject_id'],
                    'desc' => $transactionData['desc'],
                    'value' => floatval($transactionData['value']),
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
        $documentFileService = new DocumentFileService;
        DB::transaction(function () use ($documentId, $documentFileService) {
            $documentFiles = DocumentFile::where('document_id', $documentId)->get();
            foreach ($documentFiles as $documentFile) {
                $documentFileService->delete($documentFile);
            }

            Transaction::where('document_id', $documentId)->delete();
            Document::where('id', $documentId)->delete();
        });
    }

    public static function changeDocumentStatus(Document $document, User $user, string $status): void
    {
        DB::transaction(function () use ($document, $user, $status) {
            match ($status) {
                'approved' => self::approveDocument($user, $document),
                'unapproved' => self::unapproveDocument($user, $document),
                default => throw new DocumentServiceException('Invalid status'),
            };
        });
    }
}
