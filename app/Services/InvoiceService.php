<?php

namespace App\Services;

use App\DTO\InvoiceStatusDecision;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Exceptions\InvoiceServiceException;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Exception;
use Illuminate\Support\Collection;
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

        $transactionBuilder = new InvoiceTransactionBuilder($items, $invoiceData);
        $buildResult = $transactionBuilder->build();

        $createdDocument = null;
        $createdInvoice = self::createInvoiceWithoutApproval($user, $invoiceData, $items, $buildResult, $date);

        if ($approved) {

            if ($createdInvoice->invoice_type === InvoiceType::SELL) {
                $createdInvoice->update(['status' => InvoiceStatus::READY_TO_APPROVE]);

                return [
                    'document' => null,
                    'invoice' => $createdInvoice,
                ];
            }

            if (! self::getChangeStatusValidation($createdInvoice)->canProceed) {
                return [
                    'document' => null,
                    'invoice' => $createdInvoice,
                ];
            }

            $transactions = $buildResult['transactions'];

            $documentData = [
                'date' => $date,
                'title' => $invoiceData['title'] ?? (__('Invoice #').($invoiceData['number'] ?? '')),
                'number' => $invoiceData['document_number'] ?? null,
            ];

            DB::transaction(function () use ($documentData, $user, $transactions, $invoiceData, $items, &$createdDocument, &$createdInvoice) {
                $createdDocument = DocumentService::createDocument($user, $documentData, $transactions);

                $invoiceData['document_id'] = $createdDocument->id;
                $invoiceData['status'] = InvoiceStatus::APPROVED;

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
            $invoiceData['title'] = $invoiceData['title'] ?? (__('Invoice #').($invoiceData['number'] ?? ''));
            $invoiceData['status'] = $invoiceData['invoice_type'] === InvoiceType::SELL ? InvoiceStatus::PRE_INVOICE : InvoiceStatus::PENDING;

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

            if ($invoice->invoice_type === InvoiceType::SELL) {
                $invoice->update(['status' => InvoiceStatus::READY_TO_APPROVE]);

                return [
                    'document' => null,
                    'invoice' => $invoice,
                ];
            }

            if (! self::getChangeStatusValidation($invoice)->canProceed) {
                return [
                    'document' => null,
                    'invoice' => $invoice,
                ];
            }

            DB::transaction(function () use ($invoice, $invoiceData, $items, &$createdDocument) {
                $createdDocument = self::createDocumentFromInvoiceItems(auth()->user(), $invoice);

                $invoiceData['status'] = InvoiceStatus::APPROVED;
                $invoiceData['document_id'] = $createdDocument->id;
                unset($invoiceData['document_number']); // Don't update invoice with document_number

                $invoice->update($invoiceData);

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

    private static function updateInvoiceWithoutApproval(Invoice $invoice, array $invoiceData, array $items, array $buildResult, bool $approved = false)
    {
        DB::transaction(function () use ($invoice, $invoiceData, $items, $buildResult) {
            $invoiceData['vat'] = $buildResult['totalVat'];
            $invoiceData['amount'] = $buildResult['totalAmount'];
            $invoiceData['title'] = $invoiceData['title'] ?? (__('Invoice #').($invoiceData['number'] ?? ''));

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
            if ($item->itemable_type !== Product::class) {
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
            $invoice = Invoice::findOrFail($invoiceId);

            $invoice->items()->delete();

            $invoice->delete();
        });
    }

    private static function normalizeInvoiceData(array $invoiceData): array
    {
        $invoiceData = [
            'title' => $invoiceData['title'],
            'date' => $invoiceData['date'],
            'invoice_type' => $invoiceData['invoice_type'],
            'number' => isset($invoiceData['number']) ? (int) $invoiceData['number'] : null,
            'customer_id' => $invoiceData['customer_id'],
            'returned_invoice_id' => $invoiceData['returned_invoice_id'] ?? null,
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

            $vatIsValue = $item['vat_is_value'] ?? false;
            $itemVat = $vatIsValue ? floatval($item['vat'] ?? 0) : (($item['vat'] ?? 0) / 100) * ($quantity * $unitPrice - $unitDiscount);

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

            if (in_array($invoice->invoice_type, [InvoiceType::RETURN_SELL, InvoiceType::RETURN_BUY]) && $invoice->returned_invoice_id) {
                $originalItem = InvoiceItem::where('invoice_id', $invoice->returned_invoice_id)
                    ->where('itemable_type', $itemableType)->where('itemable_id', $itemableId)->first();

                if ($originalItem) {
                    $invoiceItemData['cog_after'] = $originalItem->cog_after;
                }
            }

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

    public function changeInvoiceStatus(Invoice $invoice, string $status): void
    {
        DB::transaction(function () use ($invoice, $status) {
            match ($status) {
                'ready_to_approve' => $invoice->update(['status' => InvoiceStatus::READY_TO_APPROVE]),
                'rejected' => $invoice->update(['status' => InvoiceStatus::REJECTED]),
                'approved' => $this->approveInvoice($invoice),
                'unapproved' => $this->unapproveInvoice($invoice),
                default => null,
            };
        });

    }

    /**
     * Approve the given invoice: create document, update quantities and COG.
     */
    private function approveInvoice(Invoice $invoice): void
    {
        $invoice->status = InvoiceStatus::APPROVED;
        $createdDocument = self::createDocumentFromInvoiceItems(auth()->user(), $invoice);
        $invoice->document_id = $createdDocument->id;
        $invoice->update();
        ProductService::addProductsQuantities($invoice->items->toArray(), $invoice->invoice_type);
        self::syncInvoiceItems($invoice, self::itemsFormatterForSyncingInvoiceItems($invoice));
        CostOfGoodsService::updateProductsAverageCost($invoice);
        self::syncCOGAfterForInvoiceItems($invoice);
    }

    /**
     * Unapprove the given invoice: delete document, revert quantities and COG, and unapprove ancillary costs.
     */
    private function unapproveInvoice(Invoice $invoice): void
    {
        $invoice->status = InvoiceStatus::UNAPPROVED;

        if ($invoice->document) {
            DocumentService::deleteDocument($invoice->document_id);
            $invoice->document_id = null;
        }
        $invoice->update();
        self::unapproveAncillaryCostsOfInvoice($invoice);
        ProductService::subProductsQuantities($invoice->items->toArray(), $invoice->invoice_type);
        CostOfGoodsService::updateProductsAverageCost($invoice);
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
                'vat' => $t->vat,
                'vat_is_value' => true,
                'unit' => $t->unit_price,
                'total' => $t->amount,
            ])
            ->values()
            ->all();
    }

    public static function getChangeStatusValidation(Invoice $invoice): InvoiceStatusDecision
    {

        $productIds = self::getProductIdsFromInvoice($invoice);

        $nextStatus = $invoice->status->isApproved()
            ? InvoiceStatus::UNAPPROVED
            : InvoiceStatus::APPROVED;

        return self::decideInvoiceStatusChange($invoice, $productIds, $nextStatus);

    }

    private static function getProductIdsFromInvoice(Invoice $invoice): array
    {
        $productIds = $invoice->items->where('itemable_type', Product::class)->pluck('itemable_id')->toArray();

        return $productIds;
    }

    /**
     * Validate that related invoices have the correct status before changing an invoice's status.
     */
    private static function enforceInvoiceStatusRules(Invoice $invoice, array $productIds, InvoiceStatus $nextStatus): Collection
    {
        $conflicts = collect();

        // Check for prior unapproved (BUY or SELL) invoices (soft rule - warning)
        if ($nextStatus->isApproved()) {
            $prior = self::findConflictingUnapprovedInvoices($invoice, $productIds);

            if ($prior->isNotEmpty()) {
                $conflicts->push([
                    'rule' => 'prior_unapproved_buy',
                    'invoices' => $prior,
                ]);
            }
        }

        // Check for subsequent approved invoices (BUY or SELL) (hard rule - error)
        $subsequent = self::findConflictingInvoices($invoice, $productIds, $nextStatus);

        if ($subsequent->isNotEmpty()) {
            $conflicts->push([
                'rule' => 'subsequent_approved',
                'invoices' => $subsequent,
            ]);
        }

        return $conflicts;
    }

    /**
     * Validate there are no related invoices that block changing the given invoice's status.
     *
     * @param  Invoice  $invoice  The invoice being checked (excluded from search)
     * @param  array<int>  $productIds  Product IDs to search for in related invoices
     * @param  array  $invoiceTypes  Invoice types to include in the search (e.g. BUY, SELL)
     * @param  InvoiceStatus  $nextStatus  Status value to change to
     *
     * @throws Exception If one or more related invoices are found that would prevent the status change
     */
    private static function findConflictingInvoices(Invoice $invoice, array $productIds, InvoiceStatus $nextStatus, ?array $invoiceTypes = []): Collection
    {
        if (empty($productIds)) {
            return collect();
        }

        $query = Invoice::where('number', '!=', $invoice->number)
            ->where(function ($q) use ($invoice) {
                $q->where('date', '>', $invoice->date)
                    ->orWhere(function ($sub) use ($invoice) {
                        $sub->where('date', $invoice->date)
                            ->where('number', '>', $invoice->number);
                    });
            });
        if (! empty($invoiceTypes)) {
            $query->whereIn('invoice_type', $invoiceTypes);
        }

        if (in_array($invoice->invoice_type, [InvoiceType::RETURN_BUY, InvoiceType::RETURN_SELL], true) && $invoice->returned_invoice_id) {
            $query->where('id', '!=', $invoice->returned_invoice_id);
        }

        $query->whereHas('items', fn ($q) => $q->where('itemable_type', Product::class)
            ->whereIn('itemable_id', $productIds)
        )
            ->where('status', InvoiceStatus::APPROVED);

        // Return collection of invoices (no exception here)
        return $query->get(['id', 'invoice_type', 'number', 'date']);
    }

    private static function findConflictingUnapprovedInvoices(Invoice $invoice, array $productIds, ?array $invoiceTypes = []): Collection
    {

        if (empty($productIds)) {
            return collect();
        }

        $query = Invoice::where('number', '!=', $invoice->number)
            ->where(function ($q) use ($invoice) {
                $q->where('date', '<', $invoice->date)
                    ->orWhere(function ($sub) use ($invoice) {
                        $sub->where('date', $invoice->date)
                            ->where('number', '<', $invoice->number);
                    });
            });
        if (! empty($invoiceTypes)) {
            $query->whereIn('invoice_type', $invoiceTypes);
        }
        $query->whereHas('items', fn ($q) => $q->where('itemable_type', Product::class)
            ->whereIn('itemable_id', $productIds)
        )
            ->where('status', '!=', InvoiceStatus::APPROVED);

        // Return collection of invoices (no exception here)
        return $query->get(['id', 'invoice_type', 'number', 'date']);
    }

    /**
     * Decide upon the invoice status change by aggregating rules and messages
     * Returns an InvoiceStatusDecision DTO with messages and conflicts
     */
    public static function decideInvoiceStatusChange(Invoice $invoice, array $productIds, $nextStatus): InvoiceStatusDecision
    {
        $decision = new InvoiceStatusDecision;

        self::checkReturnInvoiceRelationForStatusChange($invoice, $nextStatus, $decision);

        if (in_array($invoice->invoice_type, [InvoiceType::SELL, InvoiceType::RETURN_BUY], true) && $nextStatus->isApproved()) {
            self::checkProductsQuantityForStatusChange($invoice, $productIds, $decision);
        }

        $conflictGroups = self::enforceInvoiceStatusRules($invoice, $productIds, $nextStatus);

        foreach ($conflictGroups as $group) {
            $rule = $group['rule'];
            $invoices = $group['invoices'];

            $invoiceList = $invoices->map(fn ($inv) => $inv->invoice_type->value.': '.$inv->number)->implode(', ');

            if ($rule === 'subsequent_approved') {
                $decision->addMessage('error', __('invoices.status_change.blocked_by_subsequent', ['invoices' => $invoiceList]));
                $invoices->each(fn ($i) => $decision->addConflict($i));
            }

            if ($rule === 'prior_unapproved_buy') {
                $decision->addMessage('warning', __('invoices.status_change.prior_unapproved', ['invoices' => $invoiceList]));
                $invoices->each(fn ($i) => $decision->addConflict($i));
            }
        }

        if ($nextStatus->isApproved()) {
            self::checkAncillaryCostsForStatusChange($invoice, $decision);
        }

        return $decision;
    }

    private static function checkReturnInvoiceRelationForStatusChange(Invoice $invoice, InvoiceStatus $nextStatus, InvoiceStatusDecision $decision): void
    {
        if (in_array($invoice->invoice_type, [InvoiceType::BUY, InvoiceType::SELL], true) && $nextStatus->isUnapproved()) {
            $returnInvoice = $invoice->getReturnInvoice();
            if ($returnInvoice) {
                $decision->addMessage('error', __('invoices.status_change.blocked_by_return_invoice', [
                    'invoice' => $returnInvoice->number,
                ]));
                $decision->addConflict($returnInvoice);
            }

            return;
        }

        if (! in_array($invoice->invoice_type, [InvoiceType::RETURN_BUY, InvoiceType::RETURN_SELL], true)) {
            return;
        }

        $originalInvoice = $invoice->getReturnedInvoice();
        if (! $originalInvoice) {
            return;
        }

        $originalDateIsAfterReturnDate = $originalInvoice->date !== null && $invoice->date !== null
            ? strtotime((string) $originalInvoice->date) > strtotime((string) $invoice->date) : false;

        if ($originalDateIsAfterReturnDate) {
            $decision->addMessage('error', __('Original invoice (:invoice) date is after return invoice date. Return invoice cannot be before its original invoice.', [
                'invoice' => $originalInvoice->number,
            ]));
            $decision->addConflict($originalInvoice);

            return;
        }

        $decision->addMessage('warning', __('Return invoice is linked to original invoice (:invoice).', [
            'invoice' => $originalInvoice->number,
        ]));
        $decision->addConflict($originalInvoice);
    }

    public static function getChangeStatusDecision(Invoice $invoice, $nextStatus): InvoiceStatusDecision
    {
        $productIds = self::getProductIdsFromInvoice($invoice);

        $nextStatus = $nextStatus instanceof InvoiceStatus ? $nextStatus : InvoiceStatus::from($nextStatus);

        return self::decideInvoiceStatusChange($invoice, $productIds, $nextStatus);
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

        return $invoices->filter(fn ($inv) => ! self::getChangeStatusValidation($inv)->canProceed)->values()->all();
    }

    public static function getUnapprovedSellPriorInvoices(array $productIds, string $date, int $invoiceNumber, ?Invoice $excludeInvoice = null): array
    {
        if (empty($productIds)) {
            return [];
        }

        $query = Invoice::whereIn('invoice_type', [InvoiceType::SELL, InvoiceType::RETURN_BUY])
            ->where('status', '!=', InvoiceStatus::APPROVED)
            ->where(function ($q) use ($date, $invoiceNumber) {
                $q->where('date', '<', $date)
                    ->orWhere(function ($sub) use ($date, $invoiceNumber) {
                        $sub->where('date', $date)
                            ->where('number', '<', $invoiceNumber);
                    });
            })
            ->whereHas('items', function ($query) use ($productIds) {
                $query->whereIn('itemable_id', $productIds)
                    ->where('itemable_type', Product::class);
            });

        if ($excludeInvoice) {
            $query->where('id', '!=', $excludeInvoice->id);
        }

        return $query->get(['id', 'invoice_type', 'number'])->toArray();
    }

    private static function checkProductsQuantityForStatusChange(Invoice $invoice, array $productIds, InvoiceStatusDecision $decision): void
    {
        $conflictGroups = self::validateProductsQuantityForStatusChange($invoice, $productIds);
        $conflictsGroupedByRule = $conflictGroups->groupBy('rule');

        if ($conflictsGroupedByRule->has('oversell_allowed')) {
            $productsList = $conflictsGroupedByRule->get('oversell_allowed');

            $decision->addMessage('warning', __('product_oversell_allowed'));
            foreach ($productsList as $product) {
                $decision->addConflict($product['product']);
            }
        }

        if ($conflictsGroupedByRule->has('insufficient_quantity')) {
            $productsList = $conflictsGroupedByRule->get('insufficient_quantity');

            $decision->addMessage('error', __('insufficient_quantity_for_product'));
            foreach ($productsList as $product) {
                $productsList->each(fn ($product) => $decision->addConflict($product['product']));
            }
        }
    }

    private static function validateProductsQuantityForStatusChange(Invoice $invoice, array $productIds): Collection
    {
        $errors = collect();

        $products = Product::whereIn('id', $productIds)->get();

        foreach ($products as $product) {
            if ($invoice->status->isApproved()) {
                continue;
            }

            $invoiceItem = $invoice->items->firstWhere('itemable_id', $product->id);

            if (! $invoiceItem) {
                continue;
            }

            $requiredQuantity = $invoiceItem->quantity;
            if ($product->quantity < $requiredQuantity) {
                if ($product->oversell) {
                    $errors->push([
                        'rule' => 'oversell_allowed',
                        'product' => $product,
                    ]);
                } else {
                    $errors->push([
                        'rule' => 'insufficient_quantity',
                        'product' => $product,
                        'required' => $requiredQuantity,
                    ]);
                }
            }
        }

        return $errors;
    }

    private static function checkAncillaryCostsForStatusChange(Invoice $invoice, InvoiceStatusDecision $decision): void
    {
        $conflictsAncillaryCosts = self::enforceAncillaryCostsStatusRules($invoice);
        foreach ($conflictsAncillaryCosts as $group) {
            $rule = $group['rule'];
            $ancillaryCosts = $group['ancillary_costs'];

            $ancillaryCostList = $ancillaryCosts->map(fn ($ac) => 'ID '.$ac->id.' (Invoice ID: '.$ac->invoice_id.', Status: '.$ac->status->value.')')->implode(', ');

            if ($rule === 'subsequent_ancillary_cost_approved') {
                $decision->addMessage('error', __('invoices.status_change.blocked_by_subsequent_ancillary_cost_approved', ['ancillary_costs' => $ancillaryCostList]));
                $ancillaryCosts->each(fn ($ac) => $decision->addConflict($ac));
            }

            if ($rule === 'prior_ancillary_cost_unapproved') {
                $decision->addMessage('warning', __('invoices.status_change.prior_ancillary_cost_unapproved', ['ancillary_costs' => $ancillaryCostList]));
                $ancillaryCosts->each(fn ($ac) => $decision->addConflict($ac));
            }
        }
    }

    private static function enforceAncillaryCostsStatusRules(Invoice $invoice): Collection
    {
        $errors = collect();
        $productIds = self::getProductIdsFromInvoice($invoice);

        if ($invoice->ancillaryCosts->isEmpty()) {
            $ancillaryCosts = self::findConflictingUnapprovedAncillaryCostsForInvoiceWithNoAncillaryCosts($invoice, $productIds);
            if ($ancillaryCosts->isNotEmpty()) {
                $errors->push([
                    'rule' => 'prior_ancillary_cost_unapproved',
                    'ancillary_costs' => $ancillaryCosts,
                ]);

                return $errors;
            }
        }

        $prior = self::findConflictingUnapprovedAncillaryCosts($invoice, $productIds);
        if ($prior->isNotEmpty()) {
            $errors->push([
                'rule' => 'prior_ancillary_cost_unapproved',
                'ancillary_costs' => $prior,
            ]);
        }

        $subsequent = self::findConflictingApprovedAncillaryCosts($invoice, $productIds);
        if ($subsequent->isNotEmpty()) {
            $errors->push([
                'rule' => 'subsequent_ancillary_cost_approved',
                'ancillary_costs' => $subsequent,
            ]);
        }

        return $errors;
    }

    private static function findConflictingUnapprovedAncillaryCostsForInvoiceWithNoAncillaryCosts(Invoice $invoice, array $productIds): Collection
    {
        return \App\Models\AncillaryCost::where('status', '!=', InvoiceStatus::APPROVED)
            ->whereHas('items', function ($q) use ($productIds) {
                $q->whereIn('product_id', $productIds);
            })->whereHas('invoice', function ($q) use ($invoice) {
                $q->where('date', '<', $invoice->date)->orWhere(function ($sub) use ($invoice) {
                    $sub->where('date', $invoice->date)->where('number', '<', $invoice->number);
                });
            })->get();
    }

    private static function findConflictingUnapprovedAncillaryCosts(Invoice $invoice, array $productIds): Collection
    {
        return \App\Models\AncillaryCost::where('status', '!=', InvoiceStatus::APPROVED)
            ->whereNotIn('id', $invoice->ancillaryCosts->pluck('id')->toArray())
            ->whereHas('items', function ($q) use ($productIds) {
                $q->whereIn('product_id', $productIds);
            })->whereHas('invoice', function ($q) use ($invoice) {
                $q->where('date', '<', $invoice->date)->orWhere(function ($sub) use ($invoice) {
                    $sub->where('date', $invoice->date)->where('number', '<', $invoice->number);
                });
            })->get(['id', 'invoice_id', 'status', 'type']);
    }

    private static function findConflictingApprovedAncillaryCosts(Invoice $invoice, array $productIds): Collection
    {
        return \App\Models\AncillaryCost::where('status', InvoiceStatus::APPROVED)
            ->whereNotIn('id', $invoice->ancillaryCosts->pluck('id')->toArray())
            ->whereHas('items', function ($q) use ($productIds) {
                $q->whereIn('product_id', $productIds);
            })->whereHas('invoice', function ($q) use ($invoice) {
                $q->where('date', '>', $invoice->date)->orWhere(function ($sub) use ($invoice) {
                    $sub->where('date', $invoice->date)->where('number', '>', $invoice->number);
                });
            })->get(['id', 'invoice_id', 'status', 'type']);
    }

    // ================================
    // Form Data Transformation Methods
    // ================================

    /**
     * Extract invoice data from validated form input
     */
    public static function extractInvoiceData(array $validated): array
    {
        return [
            'title' => $validated['title'],
            'date' => $validated['date'],
            'invoice_type' => InvoiceType::from($validated['invoice_type']),
            'customer_id' => $validated['customer_id'],
            'returned_invoice_id' => $validated['returned_invoice_id'] ?? null,
            'document_number' => $validated['document_number'],
            'number' => $validated['invoice_number'],
            'subtraction' => $validated['subtractions'] ?? 0,
            'invoice_id' => $validated['invoice_id'] ?? null,
            'description' => $validated['description'] ?? null,
        ];
    }

    /**
     * Map form transactions to invoice items array
     */
    public static function mapTransactionsToItems(array $transactions, bool $vatIsValue = false): array
    {
        return collect($transactions)->map(fn ($t, $i) => [
            'transaction_index' => $i,
            'itemable_id' => $t['item_id'],
            'itemable_type' => $t['item_type'],
            'quantity' => $t['quantity'] ?? 1,
            'description' => $t['desc'] ?? null,
            'unit_discount' => $t['unit_discount'] ?? 0,
            'vat' => $t['vat'] ?? 0,
            'vat_is_value' => $vatIsValue,
            'unit' => $t['unit'] ?? 0,
            'total' => $t['total'] ?? 0,
        ])->toArray();
    }

    /**
     * Prepare transactions for view (create/edit form)
     */
    public static function prepareTransactions(?Invoice $source = null, string $mode = 'create'): Collection
    {
        if (old('transactions')) {
            return self::prepareFromOldInput();
        }

        if ($mode === 'edit' && $source instanceof Invoice) {
            return self::prepareFromInvoice($source);
        }

        return self::getEmptyTransaction();
    }

    /**
     * Restore transactions from old form input
     */
    private static function prepareFromOldInput(): Collection
    {
        return collect(old('transactions'))->map(function ($transaction, $index) {
            $transaction['id'] = $index + 1;

            if (empty($transaction['item_type']) || empty($transaction['item_id'])) {
                return $transaction;
            }

            $isProduct = $transaction['item_type'] === Product::class;
            $model = $isProduct
                ? Product::find($transaction['item_id'])
                : Service::find($transaction['item_id']);

            $transaction['subject'] = $model?->name;
            $transaction[$isProduct ? 'product_id' : 'service_id'] = $model?->id;
            $transaction['quantity'] ??= 1;

            return $transaction;
        });
    }

    /**
     * Prepare transactions from existing invoice for edit form
     */
    private static function prepareFromInvoice(Invoice $invoice): Collection
    {
        return $invoice->items->map(function ($item, $index) {
            $isProduct = isset($item->itemable->inventory_subject_id);

            return [
                'id' => $index + 1,
                'transaction_id' => $item->transaction_id,
                'desc' => $item->description,
                'quantity' => $item->quantity,
                'unit' => $item->unit_price,
                'off' => $item->unit_discount,
                'vat' => $item->vat,
                'total' => $item->amount,
                'inventory_subject_id' => $item->itemable->inventory_subject_id ?? $item->itemable->subject_id ?? null,
                'subject' => $item->itemable->name ?? null,
                'product_id' => $isProduct ? $item->itemable->id : null,
                'service_id' => $isProduct ? null : $item->itemable->id,
            ];
        });
    }

    /**
     * Get empty transaction structure for new invoice form
     */
    public static function getEmptyTransaction(): Collection
    {
        return collect([[
            'id' => 1,
            'transaction_id' => null,
            'inventory_subject_id' => null,
            'subject' => null,
            'desc' => null,
            'quantity' => 1,
            'unit' => null,
            'off' => null,
            'vat' => null,
            'total' => null,
            'product_id' => null,
            'service_id' => null,
        ]]);
    }
}
