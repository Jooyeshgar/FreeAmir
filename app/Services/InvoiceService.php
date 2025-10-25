<?php

namespace App\Services;

use App\Enums\InvoiceType;
use App\Exceptions\InvoiceServiceException;
use App\Models\AncillaryCost;
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
     * Create a document, its transactions, an invoice and invoice items.
     * The document and transactions are generated automatically from invoice data.
     *
     * @param  array  $invoiceData  - Invoice details including customer_id, date, invoice_type, etc.
     * @param  array  $items  - Invoice items with product_id, quantity, unit_discount, etc.
     * @return array ['document' => Document, 'invoice' => Invoice]
     *
     * @throws InvoiceServiceException
     */
    public static function createInvoice(User $user, array $invoiceData, array $items = [])
    {
        // Normalize invoice data
        $invoiceData = self::normalizeInvoiceData($invoiceData);

        // Build transactions using the transaction builder
        $transactionBuilder = new InvoiceTransactionBuilder($items, $invoiceData);
        $buildResult = $transactionBuilder->build();
        $transactions = $buildResult['transactions'];

        // Prepare document data
        $documentData = [
            'date' => $invoiceData['date'] ?? now()->toDateString(),
            'title' => $invoiceData['title'] ?? (__('Invoice').' '.($invoiceData['number'] ?? '')),
            'number' => $invoiceData['document_number'] ?? null,
            'creator_id' => $user->id,
            'company_id' => session('active-company-id'),
        ];

        if (empty($documentData['number'])) {
            $documentData['number'] = Document::max('number') + 1;
        }

        $createdDocument = null;
        $createdInvoice = null;

        DB::transaction(function () use ($documentData, $transactions, $invoiceData, $items, $buildResult, &$createdDocument, &$createdInvoice) {
            // Create document with transactions
            $createdDocument = DocumentService::createDocument(
                Auth::user(),
                $documentData,
                $transactions
            );

            $documentTransactions = $createdDocument->transactions->all();

            // Prepare invoice data
            $invoiceData['document_id'] = $createdDocument->id;
            $invoiceData['vat'] = $buildResult['totalVat'];
            $invoiceData['amount'] = $buildResult['totalAmount'];
            $invoiceData['creator_id'] = Auth::id();
            $invoiceData['active'] = 1;

            // Ensure invoice_type is a string value for database storage
            if ($invoiceData['invoice_type'] instanceof InvoiceType) {
                $invoiceData['invoice_type'] = $invoiceData['invoice_type']->value;
            }

            // Create invoice
            $createdInvoice = Invoice::create($invoiceData);

            // Create invoice items and link to transactions
            self::createInvoiceItems($createdInvoice, $items, $documentTransactions, $invoiceData['invoice_type']);

            // Update product quantities
            ProductService::updateProductQuantities($items, InvoiceType::from($invoiceData['invoice_type']));

            // Process costs (weighted average for both, cost_at_time_of_sale for sell)
            CostService::processInvoiceCosts($createdInvoice, InvoiceType::from($invoiceData['invoice_type']));
        });

        return [
            'document' => $createdDocument,
            'invoice' => $createdInvoice,
        ];
    }

    /**
     * Update an existing invoice and its related document/transactions.
     *
     * @return array ['document' => Document, 'invoice' => Invoice]
     *
     * @throws InvoiceServiceException
     */
    public static function updateInvoice(int $invoiceId, array $invoiceData, array $items = []): array
    {
        $invoice = Invoice::findOrFail($invoiceId);

        // // Validate invoice data (skip unique check for the current invoice number)
        // self::validateInvoiceData($invoiceData, $invoiceId);

        // Normalize invoice data
        $invoiceData = self::normalizeInvoiceData($invoiceData);

        // Build transactions using the transaction builder
        $transactionBuilder = new InvoiceTransactionBuilder($items, $invoiceData);
        $buildResult = $transactionBuilder->build();
        $transactions = $buildResult['transactions'];

        DB::transaction(function () use ($invoice, $invoiceData, $items, $transactions, $buildResult) {
            // Update document data
            $documentData = [
                'date' => $invoiceData['date'] ?? $invoice->date,
                'title' => $invoiceData['title'] ?? $invoice->document->title,
            ];

            if (isset($invoiceData['document_number'])) {
                $documentData['number'] = $invoiceData['document_number'];
            }

            // Update document
            DocumentService::updateDocument($invoice->document, $documentData);

            // Delete old transactions
            Transaction::where('document_id', $invoice->document_id)->delete();

            // Create new transactions
            foreach ($transactions as $transactionData) {
                DocumentService::createTransaction($invoice->document, $transactionData);
            }

            // Update invoice data
            $invoiceData['vat'] = $buildResult['totalVat'];
            $invoiceData['amount'] = $buildResult['totalAmount'];
            unset($invoiceData['document_number']); // Don't update invoice with document_number

            $invoice->update($invoiceData);

            // Delete old invoice items and update product quantities and the average cost
            $InvoiceItems = InvoiceItem::where('invoice_id', $invoice->id);

            $ancillaryCosts = AncillaryCost::where('invoice_id', $invoice->id)->get()->all();
            // Delete old ancillary costs if not null
            if (! empty($ancillaryCosts)) {
                foreach ($ancillaryCosts as $ancillaryCost) {
                    AncillaryCostService::deleteAncillaryCost($ancillaryCost->id);
                }
            }

            ProductService::updateProductQuantities($InvoiceItems->get()->toArray(), InvoiceType::from($invoiceData['invoice_type']), true);
            foreach ($InvoiceItems as $InvoiceItem) {
                CostService::reverseCostUpdate($InvoiceItem, $invoice->invoice_type);
            }

            $InvoiceItems->delete();

            // Create new invoice items
            $documentTransactions = $invoice->document->transactions()->get()->all();
            self::createInvoiceItems($invoice, $items, $documentTransactions, InvoiceType::from($invoiceData['invoice_type']));

            // Recreate ancillary costs if not null
            if (! empty($ancillaryCosts)) {
                AncillaryCostService::createAncillaryCost($ancillaryCosts);
            }

            // Update product quantities
            ProductService::updateProductQuantities($items, InvoiceType::from($invoiceData['invoice_type']));

            // Process costs (weighted average for both, cost_at_time_of_sale for sell)
            CostService::processInvoiceCosts($invoice, InvoiceType::from($invoiceData['invoice_type']));
        });

        return [
            'document' => $invoice->document->fresh(),
            'invoice' => $invoice->fresh(),
        ];
    }

    /**
     * Delete invoice and its related document and transactions.
     */
    public static function deleteInvoice(int $invoiceId): void
    {
        DB::transaction(function () use ($invoiceId) {
            $invoice = Invoice::find($invoiceId);
            if (! $invoice) {
                return;
            }
            // delete invoice items and update product quantities
            $invoiceItems = InvoiceItem::where('invoice_id', $invoiceId);

            $ancillaryCosts = AncillaryCost::where('invoice_id', $invoiceId)->get()->all();
            // Delete old ancillary costs if not null
            if (! empty($ancillaryCosts)) {
                foreach ($ancillaryCosts as $ancillaryCost) {
                    AncillaryCostService::deleteAncillaryCost($ancillaryCost->id);
                }
            }
            ProductService::updateProductQuantities($invoiceItems->get()->toArray(), $invoice->invoice_type, true);

            // Reverse cost updates for buy or sell invoices
            if ($invoice->invoice_type->isBuy() || $invoice->invoice_type->isSell()) {
                foreach ($invoiceItems->get() as $invoiceItem) {
                    CostService::reverseCostUpdate($invoiceItem, $invoice->invoice_type);
                }
            }

            $invoiceItems->delete();

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

    /**
     * Validate invoice data.
     *
     * @param  int|null  $invoiceId  - Pass when updating to skip unique check for current invoice
     *
     * @throws InvoiceServiceException
     */
    private static function validateInvoiceData(array $invoiceData, ?int $invoiceId = null): void
    {
        $rules = [
            'number' => 'required|numeric|min:1|unique:invoices,number'.($invoiceId ? ','.$invoiceId : ''),
            'date' => 'required|date',
            'customer_id' => 'required|integer|exists:customers,id',
            'subtraction' => 'numeric|nullable',
            'ship_date' => 'nullable|date',
            'ship_via' => 'string|nullable|max:255',
            'description' => 'string|nullable|max:255',
            'invoice_type' => 'required|string|in:buy,sell,return_buy,return_sell',
        ];

        $validator = Validator::make($invoiceData, $rules);

        if ($validator->fails()) {
            throw new InvoiceServiceException($validator->errors()->first());
        }
    }

    /**
     * Normalize invoice data (convert booleans, set defaults).
     */
    private static function normalizeInvoiceData(array $invoiceData): array
    {
        $invoiceData['subtraction'] = floatval($invoiceData['subtraction'] ?? 0);
        $invoiceData['permanent'] = isset($invoiceData['permanent']) ? (int) $invoiceData['permanent'] : 0;
        $invoiceData['active'] = isset($invoiceData['active']) ? (int) $invoiceData['active'] : 1;

        // Convert InvoiceType enum to string value if it's an enum instance
        if (isset($invoiceData['invoice_type']) && $invoiceData['invoice_type'] instanceof InvoiceType) {
            $invoiceData['invoice_type'] = $invoiceData['invoice_type']->value;
        }

        return $invoiceData;
    }

    /**
     * Create invoice items from items array.
     */
    private static function createInvoiceItems(Invoice $invoice, array $items, array $documentTransactions, InvoiceType|string $invoiceType): void
    {
        // Convert string to enum if necessary
        if (is_string($invoiceType)) {
            $invoiceType = InvoiceType::from($invoiceType);
        }

        foreach ($items as $index => $item) {
            $product = Product::findOrFail($item['product_id']);

            $quantity = $item['quantity'] ?? 1;
            $unitPrice = $item['unit'];
            $unitDiscount = $item['unit_discount'] ?? 0;

            // Calculate item discount (only on sell)
            // $itemDiscount = $invoiceType == InvoiceType::SELL ? ($unitDiscount * $quantity) : 0;

            // Calculate item VAT
            $vatRate = ($item['vat'] ?? 0) / 100;

            $itemVat = $vatRate * ($quantity * $unitPrice - $unitDiscount);

            // Calculate item amount (price - discount, VAT is separate but included in total)
            $itemAmount = $quantity * $unitPrice - $unitDiscount + $itemVat;

            // Link to the corresponding transaction (first N transactions are for items)
            $transactionId = null;
            if (isset($documentTransactions[$index])) {
                $transactionId = $documentTransactions[$index]->id;
            }

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'product_id' => $product->id,
                'transaction_id' => $transactionId,
                'quantity' => $quantity,
                'cost_at_time_of_sale' => $invoiceType->isSell() ? $product->average_cost : null,
                'unit_price' => $unitPrice,
                'unit_discount' => $unitDiscount,
                'vat' => $itemVat,
                'description' => $item['description'] ?? null,
                'amount' => $itemAmount,
            ]);
        }
    }
}
