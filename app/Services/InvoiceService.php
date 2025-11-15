<?php

namespace App\Services;

use App\Enums\InvoiceType;
use App\Exceptions\InvoiceServiceException;
use App\Models\AncillaryCost;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Service;
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
            $invoiceData['date'] = $date;

            $createdInvoice = Invoice::create($invoiceData);

            ProductService::syncProductQuantities(new Collection([]), $items, $createdInvoice->invoice_type);
            self::syncInvoiceItems($createdInvoice, $items);

            CostOfGoodsService::updateProductsAverageCost($createdInvoice);

            self::syncCOGAfterForInvoiceItems($createdInvoice);
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
            self::syncCOGAfterForInvoiceItems($invoice);
        });

        return [
            'document' => $invoice->document->fresh(),
            'invoice' => $invoice->fresh(),
        ];
    }

    public static function syncCOGAfterForInvoiceItems(Invoice $invoice)
    {
        if ($invoice->invoice_type !== InvoiceType::BUY) {
            return;
        }

        $invoiceItems = $invoice->items;

        if ($invoiceItems->isEmpty()) {
            return;
        }

        foreach ($invoiceItems as $item) {
            if (! $item->itemable) {
                continue;
            }

            $item->cog_after = $item->itemable->average_cost;
            $item->update();
        }
    }

    /**
     * Delete invoice and its related document and transactions.
     */
    public static function deleteInvoice(int $invoiceId): void
    {
        DB::transaction(function () use ($invoiceId) {
            $invoice = Invoice::find($invoiceId);

            self::checkInvoiceDeleteableOrEditable($invoice);

            self::refreshProductCOGAfterBuyInvoiceDeletion($invoice);

            $invoiceItems = $invoice->items;

            $invoice->items()->delete();

            ProductService::subProductsQuantities($invoiceItems->toArray(), $invoice->invoice_type);

            $invoice->document_id ? DocumentService::deleteDocument($invoice->document_id) : null;

            $invoice->delete();
        });
    }

    private static function refreshProductCOGAfterBuyInvoiceDeletion(Invoice $invoice): void
    {
        if ($invoice->invoice_type !== InvoiceType::BUY) {
            return;
        }

        $productIds = $invoice->items->where('itemable_type', Product::class)->pluck('itemable_id')->toArray();

        if (empty($productIds)) {
            return;
        }

        $products = Product::whereIn('id', $productIds)->get();

        foreach ($products as $product) {
            $lastInvoiceItem = InvoiceItem::whereHas('invoice', function ($query) use ($invoice, $product) {
                $query->where('invoice_type', InvoiceType::BUY)
                    ->where('date', '<', $invoice->date)
                    ->whereHas('items', function ($q) use ($product) {
                        $q->where('itemable_type', Product::class)
                            ->where('itemable_id', $product->id);
                    })->orderByDesc('date');
            })->first();

            $product->average_cost = $lastInvoiceItem ? $lastInvoiceItem->cog_after : 0;
            $product->save();
        }
    }

    private static function checkInvoiceDeleteableOrEditable(Invoice $invoice): void
    {
        if (! $invoice) {
            throw new Exception(__('Invoice not found'), 404);
        }

        if ($invoice->ancillaryCosts->isNotEmpty()) {
            throw new Exception(__('Invoice has associated ancillary costs and cannot be deleted/edited'), 400);
        }

        $productIds = $invoice->items->where('itemable_type', Product::class)->pluck('itemable_id')->toArray();

        if (empty($productIds)) {
            return;
        }

        if (self::hasSubsequentInvoicesForProduct($invoice, $productIds)) {
            throw new Exception(__('Cannot delete/edit invoice because there are subsequent invoices for the same invoice products. Please delete those invoices first.'), 400);
        }

        if (! self::isLastInvoiceForItsItems($invoice, $productIds)) {
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

    private static function normalizeInvoiceData(array $invoiceData): array
    {
        $invoiceData = [
            'title' => $invoiceData['title'],
            'date' => $invoiceData['date'],
            'invoice_type' => $invoiceData['invoice_type'],
            'number' => isset($invoiceData['number']) ? (int) $invoiceData['number'] : null,
            'customer_id' => $invoiceData['customer_id'],
            'document_number' => $invoiceData['document_number'],
            'description' => $invoiceData['description'] ?? null,
            'subtraction' => floatval($invoiceData['subtraction'] ?? 0),
            'permanent' => isset($invoiceData['permanent']) ? (int) $invoiceData['permanent'] : 0,
            'active' => isset($invoiceData['active']) ? (int) $invoiceData['active'] : 1,
            'invoice_id' => $invoiceData['invoice_id'] ?? null,
        ];

        return $invoiceData;
    }

    private static function syncInvoiceItems(Invoice $invoice, array $items): void
    {
        $itemId = [];

        foreach ($items as $item) {

            $type = $item['itemable_type'] ?? null;

            if ($type == null) {
                continue;
            }

            $product = $type === 'product' ? Product::findOrFail($item['itemable_id']) : null;
            $service = $type === 'service' ? Service::findOrFail($item['itemable_id']) : null;

            $quantity = $item['quantity'] ?? 1;
            $unitPrice = $item['unit'];
            $unitDiscount = $item['unit_discount'] ?? 0;

            // Calculate item VAT
            $vatRate = ($item['vat'] ?? 0) / 100;

            $itemVat = $vatRate * ($quantity * $unitPrice - $unitDiscount);

            // Calculate item amount (price - discount, VAT is separate but included in total)
            $itemAmount = $quantity * $unitPrice - $unitDiscount + $itemVat;

            $itemableType = $type === 'product' ? Product::class : Service::class;
            $itemableId = $item['itemable_id'];

            $invoiceItemData = [
                'invoice_id' => $invoice->id,
                'quantity' => $quantity,
                'cog_after' => $product->average_cost ?? $unitPrice,                                            // must be updated after creating invoice
                'quantity_at' => $product ? ($product->quantity > $quantity ? $product->quantity - $quantity : 0) : 0,         // quantity before this invoice
                'unit_price' => $unitPrice,
                'unit_discount' => $unitDiscount,
                'vat' => $itemVat,
                'description' => $item['description'] ?? null,
                'amount' => $itemAmount,
                'itemable_type' => $itemableType,
                'itemable_id' => $itemableId,
            ];

            $invoiceItem = InvoiceItem::updateOrCreate([
                'invoice_id' => $invoice->id,
                'itemable_id' => $itemableId,
                'itemable_type' => $itemableType,
            ], $invoiceItemData);

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
    private static function hasSubsequentInvoicesForProduct(Invoice $invoice, array $productIds): bool
    {
        $invoicesExcludingCurrent = Invoice::where('number', '!=', $invoice->number);

        if ($invoicesExcludingCurrent->count() === 0) {
            return false;
        }

        $subsequentInvoice = $invoicesExcludingCurrent->where('date', '>=', $invoice->date)
            ->whereIn('invoice_type', [InvoiceType::BUY, InvoiceType::SELL])
            ->whereHas('items', fn ($query) => $query->where('itemable_type', Product::class)
                ->whereIn('itemable_id', $productIds))
            ->exists();

        return $subsequentInvoice;
    }

    /**
     * Determines if the given sell invoice was created after the most recent buy invoice
     * that contains at least one of the same products as the sell invoice.
     *
     * This method checks if the provided invoice is of type SELL or BUY. If so, it finds the last buy invoice for its items.
     */
    private static function isLastInvoiceForItsItems(Invoice $invoice, array $productIds): bool
    {
        if (! in_array($invoice->invoice_type, [InvoiceType::SELL, InvoiceType::BUY])) {
            return false;
        }

        $query = Invoice::where('number', '!=', $invoice->number)
            ->where('date', '>', $invoice->date)
            ->whereHas('items', fn ($q) => $q->where('itemable_type', Product::class)
                ->whereIn('itemable_id', $productIds));

        if ($invoice->invoice_type === InvoiceType::SELL) {
            $query->where('invoice_type', InvoiceType::BUY);
        }

        if ($invoice->invoice_type === InvoiceType::BUY) {
            $query->whereIn('invoice_type', [InvoiceType::BUY, InvoiceType::SELL]);
        }

        return $query->doesntExist();
    }

    /**
     * Determine if an invoice can be edited or deleted without throwing, and provide the reason when it cannot.
     *
     * @return array{allowed: bool, reason: string|null}
     */
    public static function getEditDeleteStatus(Invoice $invoice): array
    {
        try {
            self::checkInvoiceDeleteableOrEditable($invoice);

            return ['allowed' => true, 'reason' => null];
        } catch (\Throwable $e) {
            return ['allowed' => false, 'reason' => $e->getMessage()];
        }
    }
}
