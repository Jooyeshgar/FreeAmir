<?php

namespace App\Services;

use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    /**
     * Create a new invoice with optional approval.
     * When approved, creates an accounting document with transactions,
     * updates product quantities, and calculates cost of goods.
     */
    public static function createInvoice(User $user, array $invoiceData, array $items = [], bool $approve = false): array
    {
        $date = $invoiceData['date'] ?? now()->toDateString();

        $transactionBuilder = new InvoiceTransactionBuilder($items, $invoiceData);
        $buildResult = $transactionBuilder->build();

        $createdDocument = null;
        $createdInvoice = null;

        DB::transaction(function () use ($user, $invoiceData, $items, $buildResult, $date, $approve, &$createdDocument, &$createdInvoice) {
            $invoiceData['vat'] = $buildResult['totalVat'];
            $invoiceData['amount'] = $buildResult['totalAmount'];
            $invoiceData['creator_id'] = $user->id;
            $invoiceData['active'] = 1;
            $invoiceData['date'] = $date;

            if ($approve) {
                $documentData = [
                    'date' => $date,
                    'title' => $invoiceData['title'] ?? (__('Invoice #').($invoiceData['number'] ?? '')),
                    'number' => $invoiceData['document_number'] ?? null,
                    'approved_at' => now(),
                    'approver_id' => $user->id,
                ];

                $createdDocument = DocumentService::createDocument($user, $documentData, $buildResult['transactions']);
                $invoiceData['document_id'] = $createdDocument->id;
                $invoiceData['status'] = \App\Enums\InvoiceStatus::APPROVED;
            }

            unset($invoiceData['document_number']);
            $createdInvoice = Invoice::create($invoiceData);

            if ($approve) {
                DocumentService::syncDocumentable($createdDocument, $createdInvoice);
                ProductService::syncProductQuantities(new Collection([]), $items, $createdInvoice->invoice_type);
            }

            self::syncInvoiceItems($createdInvoice, $items);

            if ($approve) {
                CostOfGoodsService::updateProductsAverageCost($createdInvoice);
                self::syncCOGAfterForInvoiceItems($createdInvoice);
            }
        });

        return [
            'document' => $createdDocument,
            'invoice' => $createdInvoice,
        ];
    }

    /**
     * Update an existing invoice with optional approval.
     * When approved, updates the accounting document and transactions,
     * syncs product quantities, and recalculates cost of goods.
     */
    public static function updateInvoice(int $invoiceId, array $invoiceData, array $items = [], bool $approve = false): array
    {
        $invoice = Invoice::findOrFail($invoiceId);

        self::checkInvoiceDeleteableOrEditable($invoice);

        $invoiceData = self::normalizeInvoiceData($invoiceData);

        $transactionBuilder = new InvoiceTransactionBuilder($items, $invoiceData);
        $buildResult = $transactionBuilder->build();

        DB::transaction(function () use ($invoice, $invoiceData, $items, $buildResult, $approve) {
            $invoiceData['vat'] = $buildResult['totalVat'];
            $invoiceData['amount'] = $buildResult['totalAmount'];

            if ($approve) {
                $documentData = [
                    'date' => $invoiceData['date'] ?? $invoice->date,
                    'title' => $invoiceData['title'] ?? (__('Invoice #').($invoiceData['number'] ?? '')),
                    'number' => $invoiceData['document_number'] ?? $invoice->document->number,
                    'approved_at' => now(),
                    'approver_id' => auth()->user()->id,
                ];

                if ($invoice->document) {
                    $updatedDocument = DocumentService::updateDocument($invoice->document, $documentData);
                    DocumentService::updateDocumentTransactions($invoice->document->id, $buildResult['transactions']);
                    $invoiceData['document_id'] = $updatedDocument->id;
                } else {
                    $updatedDocument = self::createDocumentFromInvoiceItems(auth()->user(), $invoice);
                    $invoiceData['document_id'] = $updatedDocument->id;
                }

                $invoiceData['status'] = \App\Enums\InvoiceStatus::APPROVED;

                $oldInvoiceItems = $invoice->items;

            } else {
                $invoiceData['status'] = $invoice->status ?? \App\Enums\InvoiceStatus::UNAPPROVED;
            }

            unset($invoiceData['document_number']);
            $invoice->update($invoiceData);

            if ($approve) {
                DocumentService::syncDocumentable($updatedDocument, $invoice);
                ProductService::syncProductQuantities($oldInvoiceItems, $items, $invoice->invoice_type);
            }

            self::syncInvoiceItems($invoice, $items);

            if ($approve) {
                $invoice->refresh();
                CostOfGoodsService::updateProductsAverageCost($invoice);
                self::syncCOGAfterForInvoiceItems($invoice);
            }
        });

        return [
            'document' => $approve ? $invoice->document->fresh() : null,
            'invoice' => $invoice,
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

            CostOfGoodsService::refreshProductCOGAfterItemsDeletion($invoice, null);

            $invoiceItems = $invoice->items;

            $invoice->items()->delete();

            ProductService::subProductsQuantities($invoiceItems->toArray(), $invoice->invoice_type);

            $invoice->document_id ? DocumentService::deleteDocument($invoice->document_id) : null;

            $invoice->delete();
        });
    }

    private static function checkInvoiceDeleteableOrEditable(Invoice $invoice): void
    {
        if (! $invoice) {
            throw new Exception(__('Invoice not found'), 404);
        }

        if ($invoice->status->isApproved()) {
            throw new Exception(__('Approved invoice cannot be deleted/edited'), 400);
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

        CostOfGoodsService::refreshProductCOGAfterItemsDeletion($invoice, $itemId);

        $invoice->items()->whereNotIn('id', $itemId)->delete();
    }

    /**
     * Determines if there are any subsequent invoices with invoice type BUY or SELL that contain any of the same products as the given invoice.
     *
     * @param  Invoice  $invoice  The invoice to check for subsequent related invoices.
     * @return bool True if a subsequent invoice exists for any of the products in the given invoice; otherwise, false.
     */
    public static function hasSubsequentInvoicesForProduct(Invoice $invoice, array $productIds): bool
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

    public function changeInvoiceStatus(Invoice $invoice, string $status): void
    {
        DB::transaction(function () use ($invoice, $status) {
            switch ($status) {
                case 'approve':
                    $invoice->status = \App\Enums\InvoiceStatus::APPROVED;
                    $document = self::createDocumentFromInvoiceItems(auth()->user(), $invoice);
                    $invoice->document_id = $document->id;
                    ProductService::addProductsQuantities($invoice->items->toArray(), $invoice->invoice_type); // increase product quantities
                    CostOfGoodsService::updateProductsAverageCost($invoice); // update COG after for invoice items
                    self::syncCOGAfterForInvoiceItems($invoice); // update COG after for invoice items
                    break;
                case 'unapprove':
                    $invoice->status = \App\Enums\InvoiceStatus::UNAPPROVED;
                    DB::transaction(function () use ($invoice) {
                        $hasDocument = $invoice->document;
                        if ($hasDocument) {
                            DocumentService::deleteDocument($invoice->document_id);
                            $invoice->document_id = null; // delete document
                        }
                        ProductService::subProductsQuantities($invoice->items->toArray(), $invoice->invoice_type); // revert product quantities
                        CostOfGoodsService::refreshProductCOGAfterItemsDeletion($invoice, null); // revert average cost of products
                        self::syncCOGAfterForInvoiceItems($invoice); // revert COG after for invoice items
                    });
                    break;
                default:
                    break;
            }

            $invoice->save();
        });
    }

    private static function createDocumentFromInvoiceItems(User $user, Invoice $invoice)
    {
        $documentData = [
            'date' => now()->toDateString(),
            'title' => $invoice->title ?? (__('Invoice #').($invoice->number ?? '')),
            'approved_at' => now(),
            'approver_id' => $user->id,
        ];

        $invoiceData = [
            'date' => $invoice->date,
            'invoice_type' => $invoice->invoice_type,
            'customer_id' => $invoice->customer_id,
            'number' => $invoice->number,
            'subtraction' => $invoice->subtraction ?? 0,
            'invoice_id' => $invoice->id ?? null,
            'description' => $invoice->description ?? null,
        ];

        $items = [];

        foreach ($invoice->items as $index => $item) {
            $subtotalBeforeVat = $item->amount - $item->vat;

            $items[] = [
                'transaction_index' => $index,
                'itemable_id' => $item->itemable_id,
                'itemable_type' => $item->itemable_type === Product::class ? 'product' : 'service',
                'quantity' => $item->quantity,
                'unit' => $item->unit_price,
                'unit_discount' => $item->unit_discount,
                'vat' => $subtotalBeforeVat > 0 ? ($item->vat / $subtotalBeforeVat) * 100 : 0,
                'description' => $item->description,
                'total' => $item->amount,
            ];
        }

        $transactionBuilder = new InvoiceTransactionBuilder($items, $invoiceData);
        $buildResult = $transactionBuilder->build();

        $document = DocumentService::createDocument($user, $documentData, $buildResult['transactions']);
        DocumentService::syncDocumentable($document, $invoice);

        return $document;
    }
}
