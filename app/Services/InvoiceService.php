<?php

namespace App\Services;

use App\Exceptions\InvoiceServiceException;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InvoiceService
{
    /**
     * Create a document, its transactions, an invoice and optional invoice items.
     * The document data is generated inside this service using invoice data and the user.
     *
     * @param User $user
     * @param array $transactions // array of arrays with keys subject_id, debit, credit, desc, created_at, updated_at
     * @param array $invoiceData
     * @param array $items // optional invoice items
     * @return array ['document' => Document, 'invoice' => Invoice, 'transactions' => Transaction[]]
     * @throws InvoiceServiceException
     */
    public static function createInvoice(User $user, array $transactions, array $invoiceData, array $items = [])
    {
        $documentData = [];
        $documentData['date'] = $invoiceData['date'] ?? now()->toDateString();
        $documentData['title'] = $invoiceData['description'] ?? (__('Invoice') . " " . ($invoiceData['code'] ?? ''));
        // number will be assigned below if empty
        $documentData['number'] = $invoiceData['document_number'] ?? null;

        // Validate invoice portion
        $invValidator = Validator::make($invoiceData, [
            'number' => 'required|string|max:255|unique:invoices,number',
            'date' => 'required|date',
            'customer_id' => 'required|integer|exists:customers,id',
            'addition' => 'numeric|nullable',
            'subtraction' => 'numeric|nullable',
            'ship_date' => 'nullable|date',
            'ship_via' => 'string|nullable|max:255',
            'description' => 'string|nullable|max:255',
            'vat' => 'numeric|nullable',
            'amount' => 'numeric|nullable',
            'is_sell' => 'required|boolean',
        ]);

        if ($invValidator->fails()) {
            throw new InvoiceServiceException($invValidator->errors()->first());
        }

        // Normalize booleans
        $invoiceData['cash_payment'] = isset($invoiceData['cash_payment']) ? (int) $invoiceData['cash_payment'] : 0;
        $invoiceData['permanent'] = isset($invoiceData['permanent']) ? (int) $invoiceData['permanent'] : 0;
        $invoiceData['is_sell'] = isset($invoiceData['is_sell']) ? (int) $invoiceData['is_sell'] : 0;
        $invoiceData['active'] = isset($invoiceData['active']) ? (int) $invoiceData['active'] : 0;

        $documentData['creator_id'] = $user->id;
        if (empty($documentData['number'])) {
            $documentData['number'] = Document::max('number') + 1;
        }

        $documentData['company_id'] = session('active-company-id');

        $createdDocument = null;
        $createdInvoice = null;
        $createdTransactions = [];

        DB::transaction(function () use ($documentData, $transactions, $invoiceData, $items, &$createdDocument, &$createdInvoice, &$createdTransactions) {
            // create document
            $createdDocument = Document::create($documentData);

            // create transactions and keep them
            foreach ($transactions as $tdata) {
                $value = 0;
                // support both value or credit/debit
                if (isset($tdata['value'])) {
                    $value = floatval($tdata['value']);
                } else {
                    $credit = floatval($tdata['credit'] ?? 0);
                    $debit = floatval($tdata['debit'] ?? 0);
                    $value = $credit - $debit;
                }

                $transaction = new Transaction();
                $transaction->subject_id = $tdata['subject_id'] ?? null;
                $transaction->desc = $tdata['desc'] ?? null;
                $transaction->value = $value;
                if (isset($tdata['created_at'])) {
                    $transaction->created_at = $tdata['created_at'];
                }
                if (isset($tdata['updated_at'])) {
                    $transaction->updated_at = $tdata['updated_at'];
                }
                $transaction->document_id = $createdDocument->id;
                $transaction->save();

                $createdTransactions[] = $transaction;
            }

            // attach document id to invoice and company if present
            $invoiceData['document_id'] = $createdDocument->id;
            if (!isset($invoiceData['company_id'])) {
                $invoiceData['company_id'] = session('active-company-id');
            }

            $createdInvoice = Invoice::create($invoiceData);

            // create invoice items if provided
            foreach ($items as $item) {
                $invoiceItem = new InvoiceItem();
                $invoiceItem->invoice_id = $createdInvoice->id;
                $invoiceItem->product_id = $item['product_id'] ?? null;
                // allow mapping by transaction_index (position in transactions array)
                if (isset($item['transaction_index']) && is_numeric($item['transaction_index'])) {
                    $idx = (int) $item['transaction_index'];
                    if (isset($createdTransactions[$idx])) {
                        $invoiceItem->transaction_id = $createdTransactions[$idx]->id;
                    }
                } elseif (isset($item['transaction_id'])) {
                    $invoiceItem->transaction_id = $item['transaction_id'];
                }

                $invoiceItem->quantity = $item['quantity'] ?? 1;
                $invoiceItem->unit_price = $item['unit_price'] ?? 0;
                $invoiceItem->unit_discount = $item['unit_discount'] ?? 0;
                $invoiceItem->vat = $item['vat'] ?? 0;
                $invoiceItem->description = $item['description'] ?? null;
                $invoiceItem->save();
            }
        });

        return [
            'document' => $createdDocument,
            'invoice' => $createdInvoice,
            'transactions' => $createdTransactions,
        ];
    }

    /**
     * Delete invoice and its related document and transactions.
     *
     * @param int $invoiceId
     * @return void
     */
    public static function deleteInvoice(int $invoiceId): void
    {
        DB::transaction(function () use ($invoiceId) {
            $invoice = Invoice::find($invoiceId);
            if (!$invoice) {
                return;
            }

            // delete invoice items
            InvoiceItem::where('invoice_id', $invoiceId)->delete();

            $documentId = $invoice->document_id;

            // delete invoice
            $invoice->delete();

            // delete document transactions and document
            if ($documentId) {
                Transaction::where('document_id', $documentId)->delete();
                Document::where('id', $documentId)->delete();
            }
        });
    }
}
