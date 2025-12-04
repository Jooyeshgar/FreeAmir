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
     * When approved, creates an accounting document with transactions, updates product quantities, and calculates cost of goods.
     */
    public static function createInvoice(User $user, array $invoiceData, array $items = [], bool $approve = false): array
    {
        $date = $invoiceData['date'] ?? now()->toDateString();

        $transactionBuilder = new InvoiceTransactionBuilder($items, $invoiceData);
        $buildResult = $transactionBuilder->build();

        $createdDocument = null;
        $createdInvoice = null;

        DB::transaction(function () use ($user, $invoiceData, $items, $buildResult, $date, $approve, &$createdDocument, &$createdInvoice) {
            $invoiceData = array_merge($invoiceData, [
                'vat' => $buildResult['totalVat'],
                'amount' => $buildResult['totalAmount'],
                'creator_id' => $user->id,
                'active' => 1,
                'date' => $date,
            ]);

            if ($approve) {
                $createdDocument = DocumentService::createDocument(
                    $user,
                    self::buildDocumentData($user, $date, $invoiceData),
                    $buildResult['transactions']
                );
                $invoiceData['document_id'] = $createdDocument->id;
                $invoiceData['status'] = \App\Enums\InvoiceAncillaryCostStatus::APPROVED;
            }

            unset($invoiceData['document_number']);
            $createdInvoice = Invoice::create($invoiceData);

            if ($approve) {
                DocumentService::syncDocumentable($createdDocument, $createdInvoice);
                ProductService::syncProductQuantities(new \Illuminate\Database\Eloquent\Collection([]), $items, $createdInvoice->invoice_type);
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
     * When approved, updates the accounting document and transactions, syncs product quantities, and recalculates cost of goods.
     */
    public static function updateInvoice(int $invoiceId, array $invoiceData, array $items = [], bool $approve = false): array
    {
        $invoice = Invoice::findOrFail($invoiceId);

        $canEditInvoice = self::getEditDeleteStatus($invoice);
        if (! $canEditInvoice['allowed']) {
            throw new Exception(__('Invoice cannot be edited:').' '.$canEditInvoice['reason'], 400);
        }

        $invoiceData = self::normalizeInvoiceData($invoiceData);

        $transactionBuilder = new InvoiceTransactionBuilder($items, $invoiceData);
        $buildResult = $transactionBuilder->build();

        DB::transaction(function () use ($invoice, $invoiceData, $items, $buildResult, $approve) {
            $invoiceData = array_merge($invoiceData, [
                'vat' => $buildResult['totalVat'],
                'amount' => $buildResult['totalAmount'],
            ]);

            if ($approve) {
                $user = auth()->user();
                $date = $invoiceData['date'] ?? $invoice->date;
                $documentNumber = $invoiceData['document_number'] ?? $invoice->document?->number;
                $documentData = self::buildDocumentData($user, $date, $invoiceData, $documentNumber);

                $updatedDocument = $invoice->document
                    ? tap(DocumentService::updateDocument($invoice->document, $documentData), fn ($doc) => DocumentService::updateDocumentTransactions($doc->id, $buildResult['transactions']))
                    : self::createDocumentFromInvoiceItems($user, $invoice);

                $invoiceData['document_id'] = $updatedDocument->id;
                $invoiceData['status'] = \App\Enums\InvoiceAncillaryCostStatus::APPROVED;
                $oldInvoiceItems = $invoice->items;

            } else {
                $invoiceData['status'] = $invoice->status ?? \App\Enums\InvoiceAncillaryCostStatus::PENDING;
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

    private static function syncCOGAfterForInvoiceItems(Invoice $invoice): void
    {
        if ($invoice->invoice_type !== InvoiceType::BUY) {
            return;
        }

        $invoice->items->filter(fn ($item) => $item->itemable)
            ->each(fn ($item) => $item->update(['cog_after' => $item->itemable->average_cost]));
    }

    /**
     * Delete invoice and its related document and transactions.
     */
    public static function deleteInvoice(int $invoiceId): void
    {
        DB::transaction(function () use ($invoiceId) {
            $invoice = Invoice::find($invoiceId);

            $canDeleteInvoice = self::getEditDeleteStatus($invoice);
            if (! $canDeleteInvoice['allowed']) {
                throw new Exception(__('Invoice cannot be deleted:').' '.$canDeleteInvoice['reason'], 400);
            }

            CostOfGoodsService::refreshProductCOGAfterItemsDeletion($invoice, null);

            $invoiceItems = $invoice->items;

            $invoice->items()->delete();

            ProductService::subProductsQuantities($invoiceItems->toArray(), $invoice->invoice_type);

            if ($invoice->document_id) {
                DocumentService::deleteDocument($invoice->document_id);
            }

            $invoice->delete();
        });
    }

    // public static function changeStatusOfSelectedInvoices(\Illuminate\Database\Eloquent\Collection $invoices, string $status): void
    // {
    //     $invoices->sortBy('date')->each(function ($invoice) use ($status) {
    //         DB::transaction(function () use ($invoice, $status) {
    //             self::canChangeInvoiceStatus($invoice);
    //             self::changeInvoiceStatus($invoice, $status);
    //         });
    //     });
    // }

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
     * @return bool True if a subsequent invoice with not approved status exists for any of the products in the given invoice; otherwise, false.
     */
    public static function hasSubsequentInvoicesNotApprovedStatusForProduct(Invoice $invoice, array $productIds): bool
    {
        $invoicesExcludingCurrent = Invoice::where('number', '!=', $invoice->number);

        if ($invoicesExcludingCurrent->count() === 0) {
            return false;
        }

        $subsequentInvoice = $invoicesExcludingCurrent->where('date', '<=', $invoice->date)
            ->whereIn('invoice_type', [InvoiceType::BUY, InvoiceType::SELL])
            ->whereHas('items', fn ($query) => $query->where('itemable_type', Product::class)
                ->whereIn('itemable_id', $productIds))
            ->where('status', '!=', \App\Enums\InvoiceAncillaryCostStatus::APPROVED)
            ->exists();

        return $subsequentInvoice;
    }

    private static function createDocumentFromInvoiceItems(User $user, Invoice $invoice)
    {
        $invoiceData = [
            'date' => $invoice->date,
            'invoice_type' => $invoice->invoice_type,
            'customer_id' => $invoice->customer_id,
            'number' => $invoice->number,
            'subtraction' => $invoice->subtraction ?? 0,
            'invoice_id' => $invoice->id ?? null,
            'description' => $invoice->description ?? null,
            'title' => $invoice->title,
        ];

        $transactionBuilder = new InvoiceTransactionBuilder(self::itemsFormatterForSyncingInvoiceItems($invoice), $invoiceData);
        $buildResult = $transactionBuilder->build();

        $document = DocumentService::createDocument($user, self::buildDocumentData($user, now()->toDateString(), $invoiceData), $buildResult['transactions']);
        DocumentService::syncDocumentable($document, $invoice);

        return $document;
    }

    private static function itemsFormatterForSyncingInvoiceItems(Invoice $invoice): array
    {
        return $invoice->items
            ->map(fn ($t, $i) => [
                'transaction_index' => $i,
                'itemable_id' => $t->itemable_id,
                'itemable_type' => $t->itemable_type === Product::class ? 'product' : 'service',
                'quantity' => $t->quantity,
                'description' => $t->description,
                'unit_discount' => $t->unit_discount,
                'vat' => ($t->amount - $t->vat) > 0 ? ($t->vat / ($t->amount - $t->vat)) * 100 : 0,
                'unit' => $t->unit_price,
                'total' => $t->amount,
            ])
            ->values()
            ->all();
    }

    /**
     * Determine if an invoice can be edited or deleted without throwing, and provide the reason when it cannot.
     */
    public static function getEditDeleteStatus(Invoice $invoice): array
    {
        try {
            self::validateInvoiceForDeleteOrEdit($invoice);

            return ['allowed' => true, 'reason' => null];
        } catch (\Throwable $e) {
            return ['allowed' => false, 'reason' => $e->getMessage()];
        }
    }

    /**
     * Validate that an invoice can be deleted or edited.
     */
    private static function validateInvoiceForDeleteOrEdit(Invoice $invoice): void
    {
        self::validateInvoiceExistance($invoice);

        if ($invoice->status->isApproved()) {
            throw new Exception(__('Approved invoice cannot be deleted/edited'), 400);
        }

        $productIds = $invoice->items->where('itemable_type', Product::class)->pluck('itemable_id')->toArray();

        if (! empty($productIds) && self::hasSubsequentInvoicesNotApprovedStatusForProduct($invoice, $productIds)) {
            throw new Exception(__('Cannot delete/edit invoice because there are subsequent invoices those are not approved for the same invoice products. Please delete those invoices first.'), 400);
        }

        if ($invoice->ancillaryCosts->isNotEmpty()) {
            throw new Exception(__('Invoice has associated ancillary costs and cannot be deleted/edited'), 400);
        }
    }

    public function changeInvoiceStatus(Invoice $invoice, string $status): void
    {
        DB::transaction(function () use ($invoice, $status) {
            match ($status) {
                'approve' => $this->toggleInvoiceApproval($invoice, true),
                'unapprove' => $this->toggleInvoiceApproval($invoice, false),
                default => null,
            };

            $invoice->save();
        });
    }

    private function toggleInvoiceApproval(Invoice $invoice, bool $approve): void
    {
        if ($approve) {
            $invoice->status = \App\Enums\InvoiceAncillaryCostStatus::APPROVED;
            $document = self::createDocumentFromInvoiceItems(auth()->user(), $invoice);
            $invoice->document_id = $document->id;
            ProductService::addProductsQuantities($invoice->items->toArray(), $invoice->invoice_type);
        } else {
            $invoice->status = \App\Enums\InvoiceAncillaryCostStatus::UNAPPROVED;

            if ($invoice->document) {
                DocumentService::deleteDocument($invoice->document_id);
                $invoice->document_id = null;
            }

            ProductService::subProductsQuantities($invoice->items->toArray(), $invoice->invoice_type);
        }

        self::syncInvoiceItems($invoice, self::itemsFormatterForSyncingInvoiceItems($invoice));

        if ($approve) {
            CostOfGoodsService::updateProductsAverageCost($invoice);
        } else {
            CostOfGoodsService::refreshProductCOGAfterItemsDeletion($invoice, null);
        }

        self::syncCOGAfterForInvoiceItems($invoice);
    }

    public static function canChangeInvoiceStatus(Invoice $invoice): bool
    {
        try {
            self::validateInvoiceExistance($invoice);
            self::validateRelatedInvoicesStatus($invoice, checkSubsequent: true);
            self::validateRelatedInvoicesStatus($invoice, checkSubsequent: false);

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Validate that an invoice exists.
     */
    private static function validateInvoiceExistance(?Invoice $invoice): void
    {
        if (! $invoice) {
            throw new Exception(__('Invoice not found'), 404);
        }
    }

    /**
     * Validate that related invoices have the correct status before changing an invoice's status.
     */
    private static function validateRelatedInvoicesStatus(Invoice $invoice, bool $checkSubsequent = true): void
    {
        $productIds = $invoice->items->where('itemable_type', Product::class)->pluck('itemable_id')->toArray();

        if (empty($productIds)) {
            return;
        }

        $query = Invoice::where('number', '!=', $invoice->number)
            ->where('date', $checkSubsequent ? '>' : '<', $invoice->date)
            ->whereIn('invoice_type', [InvoiceType::BUY, InvoiceType::SELL])
            ->whereHas('items', fn ($q) => $q->where('itemable_type', Product::class)
                ->whereIn('itemable_id', $productIds))
            ->where('status', $checkSubsequent ? '=' : '!=', \App\Enums\InvoiceAncillaryCostStatus::APPROVED);

        if ($query->exists()) {
            $message = $checkSubsequent
                ? __('Cannot change invoice status because there are subsequent invoices those are approved for the same invoice products. Please unapprove those invoices first.')
                : __('Cannot change invoice status because there are previous invoices those are not approved for the same invoice products. Please approve those invoices first.');

            throw new Exception($message, 400);
        }
    }

    /**
     * Build the document data array for creating or updating an accounting document.
     */
    private static function buildDocumentData(User $user, string $date, array $invoiceData, ?string $documentNumber = null): array
    {
        return [
            'date' => $date,
            'title' => $invoiceData['title'] ?? (__('Invoice #').($invoiceData['number'] ?? '')),
            'number' => $documentNumber ?? ($invoiceData['document_number'] ?? null),
            'approved_at' => now(),
            'approver_id' => $user->id,
        ];
    }

    /**
     * Normalize and sanitize invoice data from user input.
     */
    private static function normalizeInvoiceData(array $invoiceData): array
    {
        return [
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
    }
}
