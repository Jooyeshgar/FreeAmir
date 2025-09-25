<?php

namespace App\Services;

use App\Exceptions\InvoiceServiceException;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
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
        $documentData['title'] = $invoiceData['title'] ?? (__('Invoice') . " " . ($invoiceData['code'] ?? ''));
        // number will be assigned below if empty
        $documentData['number'] = $invoiceData['document_number'] ?? null;

        // Validate invoice portion
        $invValidator = Validator::make($invoiceData, [
            'number' => 'required|numeric|min:1|unique:invoices,number',
            'date' => 'required|date',
            // 'customer_id' => 'required|integer|exists:customers,id',
            'addition' => 'numeric|nullable',
            'subtraction' => 'numeric|nullable',
            'ship_date' => 'nullable|date',
            'ship_via' => 'string|nullable|max:255',
            'description' => 'string|nullable|max:255',
            'amount' => 'numeric|nullable',
            'is_sell' => 'required|boolean',
        ]);

        if ($invValidator->fails()) {
            throw new InvoiceServiceException($invValidator->errors()->first());
        }

        // $invoiceData['vat'] = Product:: //TODO; get this data from 1.product - 2.prodcut_group - 3.config 
        // Normalize booleans
        $invoiceData['cash_payment'] = isset($invoiceData['cash_payment']) ? (int) $invoiceData['cash_payment'] : 0;
        $invoiceData['permanent'] = isset($invoiceData['permanent']) ? (int) $invoiceData['permanent'] : 0;
        $invoiceData['active'] = isset($invoiceData['active']) ? (int) $invoiceData['active'] : 0;

        $documentData['creator_id'] = $user->id;
        if (empty($documentData['number'])) {
            $documentData['number'] = Document::max('number') + 1;
        }

        $documentData['company_id'] = session('active-company-id');

        $createdDocument = null;
        $createdInvoice = null;

        DB::transaction(function () use ($documentData, $transactions, $invoiceData, $items, &$createdDocument, &$createdInvoice) {
            // create document
            $createdDocument = DocumentService::createDocument(
                Auth::user(),
                $documentData,
                $transactions
            );

            $transactions = $createdDocument->transactions;

            // attach document id to invoice and company if present
            $invoiceData['document_id'] = $createdDocument->id;
            // initialize totals and create invoice first (with zero totals) to get id
            $invoiceData['vat'] = 0;
            $invoiceData['amount'] = 0;
            $invoiceData['creator_id'] = Auth::id();
            $invoiceData['active'] = 1;
            $createdInvoice = Invoice::create($invoiceData);

            // single pass: compute totals and create invoice items
            foreach ($items as $item) {
                $product = Product::find($item['product_id']);
                $invoiceItem = new InvoiceItem();
                $invoiceItem->invoice_id = $createdInvoice->id;
                $invoiceItem->product_id = $product->id;
                // allow mapping by transaction_index (position in transactions array)
                if (isset($item['transaction_index']) && is_numeric($item['transaction_index'])) {
                    $idx = (int) $item['transaction_index'];
                    if (isset($transactions[$idx])) {
                        $invoiceItem->transaction_id = $transactions[$idx]->id;
                    }
                }

                $invoiceItem->quantity = $item['quantity'] ?? 1;
                $invoiceItem->unit_price = $invoiceData['is_sell'] ? $product->selling_price : $product->purchace_price;
                $invoiceItem->unit_discount = $item['unit_discount'] ?? 0;
                $invoiceItem->vat = (($product->vat ?? $product->productGroup->vat ?? 0) / 100) * ($invoiceItem->quantity * ($invoiceItem->unit_price - ($invoiceData['is_sell'] ? $invoiceItem->unit_discount : 0)));
                $invoiceItem->description = $item['description'] ?? null;
                $invoiceItem->amount = $invoiceItem->quantity * $invoiceItem->unit_price - $invoiceItem->unit_discount + $invoiceItem->vat;
                $invoiceItem->save();

                // accumulate totals
                $invoiceData['vat'] += $invoiceItem->vat;
                $invoiceData['amount'] += $invoiceItem->unit_price;
            }

            // update invoice totals after single pass
            $createdInvoice->vat = $invoiceData['vat'];
            $createdInvoice->amount = $invoiceData['amount'];
            $createdInvoice->save();
        });

        return [
            'document' => $createdDocument,
            'invoice' => $createdInvoice,
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
