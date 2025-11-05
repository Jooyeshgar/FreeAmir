<?php

namespace App\Services;

use App\Enums\InvoiceType;
use App\Exceptions\InvoiceServiceException;
use App\Models\AncillaryCost;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\User;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

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
        $invoiceData = self::normalizeInvoiceData($invoiceData);

        $date = $invoiceData['date'] ?? now()->toDateString();

        // Build transactions using the transaction builder
        $transactionBuilder = new InvoiceTransactionBuilder($items, $invoiceData);
        $buildResult = $transactionBuilder->build();
        $transactions = $buildResult['transactions'];

        $documentData = [
            'date' => $date,
            'title' => $invoiceData['title'] ?? (__('Invoice').' '.($invoiceData['number'] ?? '')),
            'number' => $invoiceData['document_number'] ?? null,
        ];

        $createdDocument = null;
        $createdInvoice = null;

        DB::transaction(function () use ($documentData, $user, $transactions, $invoiceData, $items, $buildResult, $date, &$createdDocument, &$createdInvoice) {
            $createdDocument = DocumentService::createDocument($user, $documentData, $transactions);

            $invoiceData['document_id'] = $createdDocument->id;
            $invoiceData['vat'] = $buildResult['totalVat'];
            $invoiceData['amount'] = $buildResult['totalAmount'];
            $invoiceData['creator_id'] = $user->id;
            $invoiceData['active'] = 1;
            $invoiceData['number'] = $createdDocument->number;
            $invoiceData['date'] = $date;

            $createdInvoice = Invoice::create($invoiceData);

            ProductService::syncProductQuantities(new Collection([]), $items, $createdInvoice->invoice_type);
            self::syncInvoiceItems($createdInvoice, $items);

            CostOfGoodsService::updateProductsAverageCost($createdInvoice);
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

        self::checkInvoiceDeleteableOrEditable($invoice);

        $invoiceData = self::normalizeInvoiceData($invoiceData);

        $transactionBuilder = new InvoiceTransactionBuilder($items, $invoiceData);
        $buildResult = $transactionBuilder->build();
        $transactions = $buildResult['transactions'];

        DB::transaction(function () use ($invoice, $invoiceData, $items, $transactions, $buildResult) {

            $documentData = [
                'date' => $invoiceData['date'] ?? $invoice->date,
                'title' => $invoiceData['title'] ?? $invoice->document->title,
                'number' => $invoiceData['document_number'] ?? $invoice->document->number,
            ];

            DocumentService::updateDocument($invoice->document, $documentData);
            DocumentService::updateDocumentTransactions($invoice->document->id, $transactions);

            $invoiceData['vat'] = $buildResult['totalVat'];
            $invoiceData['amount'] = $buildResult['totalAmount'];
            unset($invoiceData['document_number']); // Don't update invoice with document_number

            $invoice->update($invoiceData);

            $oldInvoiceItems = $invoice->items;

            ProductService::syncProductQuantities($oldInvoiceItems, $items, $invoice->invoice_type);
            self::syncInvoiceItems($invoice, $items);

            CostOfGoodsService::updateProductsAverageCost($invoice);
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

            self::checkInvoiceDeleteableOrEditable($invoice);

            $invoiceItems = $invoice->items;

            $invoice->items()->delete();

            ProductService::subProductsQuantities($invoiceItems->toArray(), $invoice->invoice_type);

            CostOfGoodsService::updateProductsAverageCost($invoice);

            $invoice->document_id ? DocumentService::deleteDocument($invoice->document_id) : null;

            $invoice->delete();
        });
    }

    private static function checkInvoiceDeleteableOrEditable(Invoice $invoice): void
    {
        if (! $invoice) {
            throw new Exception(__('Invoice not found'), 404);
        }

        if ($invoice->ancillaryCosts->isNotEmpty()) {
            throw new Exception(__('Invoice has associated ancillary costs and cannot be deleted/edited'), 400);
        }

        if (self::hasSubsequentInvoicesForProduct($invoice)) {
            throw new Exception(__('Cannot delete/edit invoice because there are subsequent invoices for the same invoice products. Please delete those invoices first.'), 400);
        }

        if (! self::isSellInvoiceAfterLastBuyInvoice($invoice)) {
            throw new Exception(__('Cannot delete/edit invoice because it affects cost of goods sold calculations. Please review related buy invoices first.'), 400);
        }
    }

    private static function preparingAncillaryCostData(AncillaryCost $ancillaryCost): array
    {
        $data = [
            'vatPrice' => (float) $ancillaryCost->vat,
            'amount' => (float) $ancillaryCost->amount,
            'type' => $ancillaryCost->type->value,
            'vatPercentage' => $ancillaryCost->vat_percentage,
            'date' => $ancillaryCost->date,
            'invoice_id' => $ancillaryCost->invoice_id,
            'company_id' => session('active-company-id'),
        ];

        $ancillaryCostItems = $ancillaryCost->items->toArray() ?? [];
        $processedCosts = [];
        $total = 0;
        if (! empty($ancillaryCostItems)) {
            foreach ($ancillaryCostItems as $key => $cost) {

                $amount = convertToFloat($cost['amount'] ?? 0);
                if ($amount >= 0) {
                    $processedCosts[] = [
                        'product_id' => $cost['product_id'] ?? null,
                        'amount' => $amount,
                    ];
                }
                $total += $amount;
            }
        }
        $data['ancillaryCosts'] = $processedCosts;

        return $data;
    }

    /**
     * Normalize invoice data (convert booleans, set defaults).
     */
    private static function normalizeInvoiceData(array $invoiceData): array
    {
        $invoiceData = [
            'subtraction' => floatval($invoiceData['subtraction'] ?? 0),
            'permanent' => isset($invoiceData['permanent']) ? (int) $invoiceData['permanent'] : 0,
            'active' => isset($invoiceData['active']) ? (int) $invoiceData['active'] : 1,
            'invoice_type' => $invoiceData['invoice_type'],
            'customer_id' => $invoiceData['customer_id'],
        ];

        return $invoiceData;
    }

    private static function syncInvoiceItems(Invoice $invoice, array $items): void
    {
        $itemId = [];

        foreach ($items as $item) {
            $product = Product::findOrFail($item['product_id']);

            $quantity = $item['quantity'] ?? 1;
            $unitPrice = $item['unit'];
            $unitDiscount = $item['unit_discount'] ?? 0;

            // Calculate item VAT
            $vatRate = ($item['vat'] ?? 0) / 100;

            $itemVat = $vatRate * ($quantity * $unitPrice - $unitDiscount);

            // Calculate item amount (price - discount, VAT is separate but included in total)
            $itemAmount = $quantity * $unitPrice - $unitDiscount + $itemVat;

            $invoiceItem = InvoiceItem::updateOrCreate([
                'id' => $item['id'] ?? null,
            ], [
                'invoice_id' => $invoice->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'cog_after' => $product->average_cost ?? $unitPrice,                                            // must be updated after creating invoice
                'quantity_at' => ($product->quantity > $quantity) ? $product->quantity - $quantity : 0,         // quantity before this invoice
                'unit_price' => $unitPrice,
                'unit_discount' => $unitDiscount,
                'vat' => $itemVat,
                'description' => $item['description'] ?? null,
                'amount' => $itemAmount,
            ]);
            $itemId[] = $invoiceItem->id;
        }
        $invoice->items()->whereNotIn('id', $itemId)->delete();
    }

    /**
     * Determines if there are any subsequent invoices with invoice type BUY or SELL that contain any of the same products as the given invoice.
     *
     * @param  Invoice  $invoice  The invoice to check for subsequent related invoices.
     * @return bool True if a subsequent invoice exists for any of the products in the given invoice; otherwise, false.
     */
    private static function hasSubsequentInvoicesForProduct(Invoice $invoice): bool
    {
        $subsequentInvoice = Invoice::where('date', '>', $invoice->date)
            ->whereIn('invoice_type', [InvoiceType::BUY, InvoiceType::SELL])
            ->whereHas('items', fn ($query) => $query->where('product_id', $invoice->items->pluck('product_id')->toArray()))
            ->orderByDesc('date')->first();

        return $subsequentInvoice !== null;
    }

    /**
     * Determines if the given sell invoice was created after the most recent buy invoice
     * that contains at least one of the same products as the sell invoice.
     *
     * This method checks if the provided invoice is of type SELL. If so, it finds the latest
     * BUY invoice that includes any of the products present in the sell invoice's items.
     * It then compares the dates of the sell invoice and the last relevant buy invoice.
     *
     * @param  Invoice  $invoice  The invoice to check.
     * @return bool True if the sell invoice date is after the last relevant buy invoice date, false otherwise.
     */
    private static function isSellInvoiceAfterLastBuyInvoice(Invoice $invoice): bool
    {
        if ($invoice->invoice_type === InvoiceType::SELL) {
            $lastBuyInvoice = Invoice::where('invoice_type', InvoiceType::BUY)
                ->whereHas('items', fn ($query) => $query->where('product_id', $invoice->items->pluck('product_id')->toArray()))
                ->orderByDesc('date')
                ->first();

            if ($lastBuyInvoice && $invoice->date > $lastBuyInvoice->date) {
                return true;
            }
        }

        return false;
    }
}
