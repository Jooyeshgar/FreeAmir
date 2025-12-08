<?php

namespace App\Services;

use App\Enums\InvoiceAncillaryCostStatus;
use App\Enums\InvoiceType;
use App\Exceptions\InvoiceServiceException;
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
     * Create a document, its transactions, an invoice and invoice items.
     * The document and transactions are generated automatically from invoice data.
     *
     * @param  array  $invoiceData  - Invoice details including customer_id, date, invoice_type, etc.
     * @param  array  $items  - Invoice items with product_id, quantity, unit_discount, etc.
     * @return array ['document' => Document, 'invoice' => Invoice]
     *
     * @throws InvoiceServiceException
     */
    public static function createInvoice(User $user, array $invoiceData, array $items = [], bool $approved = false): array
    {
        $date = $invoiceData['date'] ?? now()->toDateString();

        // Build transactions using the transaction builder
        $transactionBuilder = new InvoiceTransactionBuilder($items, $invoiceData);
        $buildResult = $transactionBuilder->build();
        $transactions = $buildResult['transactions'];

        $documentData = [
            'date' => $date,
            'title' => $invoiceData['title'] ?? (__('Invoice #').($invoiceData['number'] ?? '')),
            'number' => $invoiceData['document_number'] ?? null,
        ];

        $createdDocument = null;
        $createdInvoice = self::createInvoiceWithoutApproval($user, $invoiceData, $items, $buildResult, $date);

        if ($approved) {

            DB::transaction(function () use ($documentData, $user, $transactions, $invoiceData, $items, &$createdDocument, &$createdInvoice) {
                $createdDocument = DocumentService::createDocument($user, $documentData, $transactions);

                $invoiceData['document_id'] = $createdDocument->id;
                $invoiceData['status'] = InvoiceAncillaryCostStatus::APPROVED;

                $createdInvoice->update($invoiceData);

                DocumentService::syncDocumentable($createdDocument, $createdInvoice);

                ProductService::addProductsQuantities($items, $createdInvoice->invoice_type);
                self::syncInvoiceItems($createdInvoice, $items);

                $createdInvoice->refresh();
                CostOfGoodsService::updateProductsAverageCost($createdInvoice);

                self::syncCOGAfterForInvoiceItems($createdInvoice);
            });
        }

        return [
            'document' => $createdDocument,
            'invoice' => $createdInvoice,
        ];
    }

    private static function createInvoiceWithoutApproval(User $user, array $invoiceData, array $items, array $buildResult, string $date)
    {
        $createdInvoice = null;

        DB::transaction(function () use ($user, $invoiceData, $items, $buildResult, $date, &$createdDocument, &$createdInvoice) {
            $invoiceData['document_id'] = null;
            $invoiceData['vat'] = $buildResult['totalVat'];
            $invoiceData['amount'] = $buildResult['totalAmount'];
            $invoiceData['creator_id'] = $user->id;
            $invoiceData['date'] = $date;

            $createdInvoice = Invoice::create($invoiceData);

            self::syncInvoiceItems($createdInvoice, $items);
        });

        return $createdInvoice;
    }

    /**
     * Update an existing invoice and its related document/transactions.
     *
     * @return array ['document' => Document, 'invoice' => Invoice]
     *
     * @throws InvoiceServiceException
     */
    public static function updateInvoice(int $invoiceId, array $invoiceData, array $items = [], bool $approved = false): array
    {
        $invoice = Invoice::findOrFail($invoiceId);

        $invoiceData = self::normalizeInvoiceData($invoiceData);

        $transactionBuilder = new InvoiceTransactionBuilder($items, $invoiceData);
        $buildResult = $transactionBuilder->build();

        $createdDocument = null;
        $invoice = self::updateInvoiceWithoutApproval($invoice, $invoiceData, $items, $buildResult);

        if ($approved) {

            DB::transaction(function () use ($invoice, $invoiceData, $items, &$createdDocument) {

                $createdDocument = self::createDocumentFromInvoiceItems(auth()->user(), $invoice);

                $invoiceData['status'] = InvoiceAncillaryCostStatus::APPROVED;
                $invoiceData['document_id'] = $createdDocument->id;
                unset($invoiceData['document_number']); // Don't update invoice with document_number

                $invoice->update($invoiceData);

                // Remove old quantities
                ProductService::subProductsQuantities(self::itemsFormatterForSyncingInvoiceItems($invoice), $invoice->invoice_type);

                // Add new quantities
                ProductService::addProductsQuantities($items, $invoice->invoice_type);

                self::syncInvoiceItems($invoice, $items);

                $invoice->refresh();

                CostOfGoodsService::updateProductsAverageCost($invoice);
                self::syncCOGAfterForInvoiceItems($invoice);
            });
        }

        return [
            'document' => $createdDocument,
            'invoice' => $invoice,
        ];
    }

    private static function updateInvoiceWithoutApproval(Invoice $invoice, array $invoiceData, array $items, array $buildResult)
    {
        DB::transaction(function () use ($invoice, $invoiceData, $items, $buildResult) {
            $invoiceData['vat'] = $buildResult['totalVat'];
            $invoiceData['amount'] = $buildResult['totalAmount'];

            $invoice->update($invoiceData);

            self::syncInvoiceItems($invoice, $items);
        });

        return $invoice;
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
        self::validateInvoiceExistance($invoice);

        if ($invoice->status->isApproved()) {
            throw new Exception(__('Approved invoices cannot be deleted/edited'), 400);
        }

        if ($invoice->ancillaryCosts()->exists() && $invoice->ancillaryCosts->every(fn ($ac) => $ac->status->isApproved())) {
            throw new Exception(__('Invoice has associated approved ancillary costs and cannot be deleted/edited'), 400);
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
            match ($status) {
                'approve' => $this->toggleInvoiceApproval($invoice, true),
                'unapprove' => $this->toggleInvoiceApproval($invoice, false),
                default => null,
            };
        });

    }

    private function toggleInvoiceApproval(Invoice $invoice, bool $approve): void
    {
        if ($approve) {
            $invoice->status = InvoiceAncillaryCostStatus::APPROVED;
            $createdDocument = self::createDocumentFromInvoiceItems(auth()->user(), $invoice);
            $invoice->document_id = $createdDocument->id;
            $invoice->update();
            ProductService::addProductsQuantities($invoice->items->toArray(), $invoice->invoice_type);
            self::syncInvoiceItems($invoice, self::itemsFormatterForSyncingInvoiceItems($invoice));
            CostOfGoodsService::updateProductsAverageCost($invoice);
            self::syncCOGAfterForInvoiceItems($invoice);
        } else {
            $invoice->status = InvoiceAncillaryCostStatus::UNAPPROVED;

            if ($invoice->document) {
                DocumentService::deleteDocument($invoice->document_id);
                $invoice->document_id = null;
            }
            $invoice->update();
            self::unapproveAncillaryCostsOfInvoice($invoice);
            ProductService::subProductsQuantities($invoice->items->toArray(), $invoice->invoice_type);
            CostOfGoodsService::updateProductsAverageCost($invoice);
        }
    }

    private function unapproveAncillaryCostsOfInvoice(Invoice $invoice): void
    {
        if (! $invoice->ancillaryCosts()->exists()) {
            return;
        }

        $AncillaryCostService = new AncillaryCostService;
        foreach ($invoice->ancillaryCosts as $ancillaryCost) {
            if (! $ancillaryCost->status->isApproved()) {
                continue;
            }
            $AncillaryCostService->changeAncillaryCostStatus($ancillaryCost, 'unapprove');
        }
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

        $documentData = [
            'date' => $invoiceData['date'] ?? now()->toDateString(),
            'title' => $invoiceData['title'] ?? (__('Invoice #').($invoiceData['number'] ?? '')),
            'approved_at' => now(),
            'approver_id' => $user->id,
        ];

        $document = DocumentService::createDocument($user, $documentData, $buildResult['transactions']);
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

    public static function getChangeStatusValidation(Invoice $invoice): array
    {
        try {
            self::validateInvoiceExistance($invoice);
            $productIds = self::getProductIdsFromInvoice($invoice);
            self::validateProductsQuantityForStatusChange($invoice, $productIds);

            self::validateRelatedInvoicesStatus($invoice, checkSubsequent: true, productIds: $productIds);
            self::validateRelatedInvoicesStatus($invoice, checkSubsequent: false, productIds: $productIds);
            self::validateNoApprovedInvoicesWithSameProductsAfterInvoiceDate($invoice, $productIds);

            return ['allowed' => true, 'reason' => null];
        } catch (\Throwable $e) {
            return ['allowed' => false, 'reason' => $e->getMessage()];
        }
    }

    private static function validateProductsQuantityForStatusChange(Invoice $invoice, array $productIds): void
    {
        if ($invoice->invoice_type !== InvoiceType::SELL) {
            return;
        }

        $products = Product::whereIn('id', $productIds)->get();

        foreach ($products as $product) {
            if ($product->oversell) {
                continue;
            }

            if ($invoice->status->isApproved()) {
                continue;
            }

            $invoiceItem = $invoice->items->firstWhere('itemable_id', $product->id);

            if (! $invoiceItem) {
                continue;
            }

            $requiredQuantity = $invoiceItem->quantity;
            if ($product->quantity < $requiredQuantity) {
                throw new Exception(__('Insufficient quantity for product ":product". Available: :available, Required: :required.', [
                    'product' => $product->name,
                    'available' => $product->quantity,
                    'required' => $requiredQuantity,
                ]), 400);
            }
        }
    }

    private static function validateNoApprovedInvoicesWithSameProductsAfterInvoiceDate(Invoice $invoice, array $productIds): void
    {
        $exists = Invoice::where('status', InvoiceAncillaryCostStatus::APPROVED)
            ->where('date', '>', $invoice->date)
            ->whereHas('items', function ($query) use ($productIds) {
                $query->where('itemable_type', Product::class)
                    ->whereIn('itemable_id', $productIds);
            })
            ->exists();

        if ($exists) {
            throw new Exception(__('Cannot change invoice status because there are subsequent invoices those are approved for the same invoice products. Please unapprove those invoices first.'), 400);
        }
    }

    private static function getProductIdsFromInvoice(Invoice $invoice): array
    {
        $productIds = $invoice->items->where('itemable_type', Product::class)->pluck('itemable_id')->toArray();

        if (empty($productIds)) {
            throw new Exception(__('No products associated with this invoice.'), 400);
        }

        return $productIds;
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
    private static function validateRelatedInvoicesStatus(Invoice $invoice, bool $checkSubsequent, array $productIds): void
    {
        $comparison = function ($q) use ($invoice, $checkSubsequent) {
            $operator = $checkSubsequent ? '>' : '<';

            $q->where('date', $operator, $invoice->date)
                ->orWhere(function ($sub) use ($invoice, $operator) {
                    $sub->where('date', $invoice->date)
                        ->where('number', $operator, $invoice->number);
                });
        };

        $query = Invoice::where('number', '!=', $invoice->number)
            ->where($comparison)
            ->whereIn('invoice_type', [InvoiceType::BUY, InvoiceType::SELL])
            ->whereHas('items', fn ($q) => $q->where('itemable_type', Product::class)
                ->whereIn('itemable_id', $productIds)
            )
            ->where('status',
                $checkSubsequent ? InvoiceAncillaryCostStatus::APPROVED
                                 : '!=', InvoiceAncillaryCostStatus::APPROVED
            );

        if ($query->exists()) {
            $message = $checkSubsequent
                ? __('Cannot change invoice status because there are subsequent invoices those are approved for the same invoice products. Please unapprove those invoices first.')
                : __('Cannot change invoice status because there are previous invoices those are not approved for the same invoice products. Please approve those invoices first.');

            throw new Exception($message, 400);
        }
    }

    public static function notAllowedInvoiceForAncillaryCosts(Invoice $invoice, array $productIds): array
    {
        if (! $invoice->status->isApproved()) {
            return [];
        }

        $invoices = Invoice::where('date', '>=', $invoice->date)
            ->whereHas('items', function ($query) use ($productIds) {
                $query->where('itemable_type', Product::class)
                    ->whereIn('itemable_id', $productIds);
            })->get();

        return $invoices->filter(fn ($inv) => ! self::getChangeStatusValidation($inv)['allowed'])->values()->all();
    }
}
