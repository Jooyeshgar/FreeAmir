<?php

namespace App\Services;

use App\Enums\AncillaryCostType;
use App\Enums\InvoiceAncillaryCostStatus;
use App\Enums\InvoiceType;
use App\Models\AncillaryCost;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Service for handling ancillary costs.
 * Ancillary costs are additional costs related to purchasing goods such as:
 * - Transportation costs
 * - Insurance
 * - Customs fees
 * - Loading/unloading costs
 *
 * These costs must be distributed across invoice items and added to the average cost.
 */
class AncillaryCostService
{
    /**
     * Create an ancillary cost with its items and distribute the amount across the related products.
     */
    public static function createAncillaryCost(User $user, array $data, bool $approved = false)
    {
        self::validateAncillaryCostData($data);

        $invoice = Invoice::findOrFail($data['invoice_id']);

        $document = null;
        $ancillaryCost = self::createAncillaryCostWithoutApproval($data);

        if ($approved) {

            if (! self::getChangeStatusValidation($ancillaryCost)['allowed']) {
                return [
                    'document' => null,
                    'ancillaryCost' => $ancillaryCost,
                ];
            }

            $type = AncillaryCostType::from($data['type']);

            $documentData = [
                'date' => $data['date'] ?? now()->toDateString(),
                'title' => $type->label().' '.__('Invoice #').(formatDocumentNumber($invoice->number) ?? ''),
            ];

            $transactionBuilder = new AncillaryCostTransactionBuilder($data);
            $transactions = $transactionBuilder->build();

            DB::transaction(function () use ($documentData, $user, $invoice, $transactions, $ancillaryCost, &$document) {
                $document = DocumentService::createDocument(
                    $user,
                    $documentData,
                    $transactions
                );

                $ancillaryCost->update([
                    'document_id' => $document->id,
                    'status' => InvoiceAncillaryCostStatus::APPROVED,
                ]);

                DocumentService::syncDocumentable($document, $ancillaryCost);

                CostOfGoodsService::updateProductsAverageCost($invoice);
                self::syncCOGAfterAncillarityCost($invoice);
            });
        }

        return [
            'document' => $document,
            'ancillaryCost' => $ancillaryCost,
        ];
    }

    private static function createAncillaryCostWithoutApproval(array $data)
    {
        $ancillaryCost = null;
        DB::transaction(function () use ($data, &$ancillaryCost) {
            $ancillaryCost = AncillaryCost::create([
                'invoice_id' => $data['invoice_id'],
                'customer_id' => $data['customer_id'],
                'company_id' => $data['company_id'],
                'date' => $data['date'] ?? now()->toDateString(),
                'type' => AncillaryCostType::from($data['type']),
                'amount' => $data['amount'],
                'vat' => $data['vatPrice'] ?? 0,
            ]);

            self::syncAncillaryCostItems($ancillaryCost, $data['ancillaryCosts'] ?? []);
        });

        return $ancillaryCost;
    }

    private static function syncCOGAfterAncillarityCost($invoice)
    {
        $invoiceItems = $invoice->items;
        if ($invoiceItems->isEmpty()) {
            return;
        }

        foreach ($invoiceItems as $item) {
            $item->cog_after = $item->itemable->average_cost;
            $item->update();
        }
    }

    public static function updateAncillaryCost(User $user, AncillaryCost $ancillaryCost, array $data, bool $approve = false)
    {
        self::validateAncillaryCostData($data);

        $createdDocument = null;
        $ancillaryCost = self::updateAncillaryCostWithoutApproval($ancillaryCost, $data);

        if ($approve) {

            if (! self::getChangeStatusValidation($ancillaryCost)['allowed']) {
                return [
                    'document' => null,
                    'ancillaryCost' => $ancillaryCost,
                ];
            }

            DB::transaction(function () use ($ancillaryCost, &$createdDocument) {

                $createdDocument = self::createDocumentFromAncillaryCostItems(auth()->user(), $ancillaryCost);

                $ancillaryCost->update([
                    'document_id' => $createdDocument->id,
                    'status' => InvoiceAncillaryCostStatus::APPROVED,
                ]);

                CostOfGoodsService::updateProductsAverageCost($ancillaryCost->invoice);
                self::syncCOGAfterAncillarityCost($ancillaryCost->invoice);
            });
        }

        return [
            'document' => $createdDocument,
            'ancillaryCost' => $ancillaryCost,
        ];
    }

    private static function updateAncillaryCostWithoutApproval(AncillaryCost $ancillaryCost, array $data)
    {
        DB::transaction(function () use ($ancillaryCost, $data) {
            $type = AncillaryCostType::from($data['type']);

            $ancillaryCost->update([
                'invoice_id' => $data['invoice_id'],
                'customer_id' => $data['customer_id'],
                'date' => $data['date'],
                'type' => $type,
                'amount' => $data['amount'],
                'vat' => $data['vatPrice'] ?? 0,
            ]);

            self::syncAncillaryCostItems($ancillaryCost, $data['ancillaryCosts'] ?? []);
        });

        return $ancillaryCost;
    }

    /**
     * Delete an ancillary cost and reverse its distribution.
     *
     * @param  int  $ancillaryCostId  The ID of the ancillary cost
     *
     * @throws \Exception
     */
    public static function deleteAncillaryCost(AncillaryCost $ancillaryCost): void
    {
        DB::transaction(function () use ($ancillaryCost) {
            $invoice = $ancillaryCost->invoice;

            if ($ancillaryCost->document) {
                DocumentService::deleteDocument($ancillaryCost->document_id);
            }

            $ancillaryCost->items()->delete();
            $ancillaryCost->delete();

            CostOfGoodsService::updateProductsAverageCost($invoice);

            self::syncCOGAfterAncillarityCost($invoice);
        });
    }

    private static function syncAncillaryCostItems(AncillaryCost $ancillaryCost, array $items): void
    {
        $itemIds = [];

        foreach ($items as $item) {
            $ancillaryCostItem = $ancillaryCost->items()->updateOrCreate(
                [
                    'product_id' => $item['product_id'],
                ],
                [
                    'type' => $ancillaryCost->type,
                    'amount' => $item['amount'],
                ]
            );

            $itemIds[] = $ancillaryCostItem->id;
        }

        $ancillaryCost->items()->whereNotIn('id', $itemIds)->delete();
    }

    /**
     * Validate ancillary cost data.
     *
     * @param  array  $data  Data to validate
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    private static function validateAncillaryCostData(array $data): void
    {
        $allowedTypes = collect(AncillaryCostType::cases())->map->value->all();

        $validator = Validator::make($data, [
            'invoice_id' => 'required|integer|exists:invoices,id',
            'vatPrice' => 'nullable|numeric|min:0',
            'vatPercentage' => 'nullable|numeric|min:0|max:100',
            'date' => 'required|date',
            'type' => ['required', Rule::in($allowedTypes)],
            'amount' => 'required|numeric|min:0',
            'ancillaryCosts' => 'required|array',
            'ancillaryCosts.*.product_id' => 'required_with:ancillaryCosts|integer|exists:products,id',
            'ancillaryCosts.*.amount' => 'required_with:ancillaryCosts|numeric|min:0',
        ]);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }
    }

    /**
     * Determine if an ancillary cost can be edited or deleted without throwing, and provide the reason when it cannot.
     *
     * @return array{allowed: bool, reason: string|null}
     */
    public static function getEditDeleteStatus(AncillaryCost $ancillaryCost): array
    {
        try {
            self::checkAncillaryCostDeleteableOrEditable($ancillaryCost);

            return ['allowed' => true, 'reason' => null];
        } catch (\Throwable $e) {
            return ['allowed' => false, 'reason' => $e->getMessage()];
        }
    }

    public function changeAncillaryCostStatus(AncillaryCost $ancillaryCost, string $status): void
    {
        DB::transaction(function () use ($ancillaryCost, $status) {
            match ($status) {
                'approve' => $this->toggleInvoiceApproval($ancillaryCost, true),
                'unapprove' => $this->toggleInvoiceApproval($ancillaryCost, false),
                default => null,
            };
        });
    }

    private function toggleInvoiceApproval(AncillaryCost $ancillaryCost, bool $approve): void
    {
        if ($approve) {
            $ancillaryCost->status = InvoiceAncillaryCostStatus::APPROVED;

            $createdDocument = self::createDocumentFromAncillaryCostItems(auth()->user(), $ancillaryCost);
            $ancillaryCost->document_id = $createdDocument->id;
            $ancillaryCost->update();
            self::syncAncillaryCostItems($ancillaryCost, self::itemsFormatterForSyncingAncillaryCostItems($ancillaryCost));
            CostOfGoodsService::updateProductsAverageCost($ancillaryCost->invoice);
            self::syncCOGAfterAncillarityCost($ancillaryCost->invoice);
        } else {
            $ancillaryCost->status = InvoiceAncillaryCostStatus::UNAPPROVED;

            if ($ancillaryCost->document) {
                DocumentService::deleteDocument($ancillaryCost->document_id);
                $ancillaryCost->document_id = null;
            }
            $ancillaryCost->update();
            CostOfGoodsService::updateProductsAverageCost($ancillaryCost->invoice);
            self::syncCOGAfterAncillarityCost($ancillaryCost->invoice);
        }
    }

    private static function itemsFormatterForSyncingAncillaryCostItems(AncillaryCost $ancillaryCost): array
    {
        $formattedItems = [];

        foreach ($ancillaryCost->items as $item) {
            $formattedItems[] = [
                'product_id' => $item->product_id,
                'type' => $ancillaryCost->type,
                'amount' => $item->amount,
            ];
        }

        return $formattedItems;
    }

    private static function createDocumentFromAncillaryCostItems(User $user, AncillaryCost $ancillaryCost)
    {
        $ancillaryCostData = [
            'invoice_id' => $ancillaryCost->invoice_id,
            'customer_id' => $ancillaryCost->customer_id,
            'date' => $ancillaryCost->date ?? now()->toDateString(),
            'type' => $ancillaryCost->type,
            'amount' => $ancillaryCost->amount,
            'vatPrice' => $ancillaryCost->vat ?? 0,
            'ancillaryCosts' => $ancillaryCost->items->map(fn ($item, $index) => [
                'transaction_index' => $index,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'unit' => $item->unit_price,
                'unit_discount' => $item->unit_discount,
                'description' => $item->description,
                'amount' => $item->amount,
            ])->toArray(),
        ];

        $transactions = (new AncillaryCostTransactionBuilder($ancillaryCostData))->build();

        $document = DocumentService::createDocument($user, [
            'date' => $ancillaryCostData['date'],
            'title' => $ancillaryCost->title ?? (__('Ancillary Cost #').($ancillaryCost->number ?? '')),
            'approved_at' => now(),
            'approver_id' => $user->id,
        ], $transactions);

        DocumentService::syncDocumentable($document, $ancillaryCost);

        return $document;
    }

    public static function getChangeStatusValidation(AncillaryCost $ancillaryCost): array
    {
        try {
            $productIds = self::getProductIdsFromAncillaryCost($ancillaryCost);

            self::validateAncillaryCostInvoiceApproval($ancillaryCost);
            self::validateRelatedAncillaryCostStatus($ancillaryCost, $productIds);
            self::validateNoApprovedInvoicesAfterAncillaryCostWithSameProducts($ancillaryCost, $productIds);

            return ['allowed' => true, 'reason' => null];
        } catch (\Throwable $e) {
            return ['allowed' => false, 'reason' => $e->getMessage()];
        }
    }

    private static function getProductIdsFromAncillaryCost(AncillaryCost $ancillaryCost): array
    {
        $productIds = $ancillaryCost->items->pluck('product_id')->toArray();

        if (empty($productIds)) {
            throw new Exception(__('No products associated with this ancillary cost.'), 400);
        }

        return $productIds;
    }

    private static function validateNoApprovedInvoicesAfterAncillaryCostWithSameProducts(AncillaryCost $ancillaryCost, array $productIds): void
    {
        $query = Invoice::where('date', '>', $ancillaryCost->date)
            ->where('status', InvoiceAncillaryCostStatus::APPROVED)
            ->whereHas('items', fn ($q) => $q->whereIn('itemable_id', $productIds)->where('itemable_type', Product::class));

        if ($query->exists()) {
            throw new Exception(__('Cannot change ancillary cost status because there are subsequent approved invoices for the same products. Please unapprove those invoices first.'), 400);
        }
    }

    private static function checkAncillaryCostDeleteableOrEditable(AncillaryCost $ancillaryCost): void
    {
        if ($ancillaryCost->status->isApproved()) {
            throw new Exception(__('Approved Ancillary Cost cannot be edited/deleted'), 400);
        }
    }

    private static function validateAncillaryCostInvoiceApproval(AncillaryCost $ancillaryCost): void
    {
        if (! $ancillaryCost->invoice->status->isApproved()) {
            throw new Exception(__('Cannot change status of Ancillary Cost linked to unapproved Invoice'), 400);
        }
    }

    private static function validateRelatedAncillaryCostStatus(AncillaryCost $ancillaryCost, array $productIds): void
    {
        $query = AncillaryCost::where('id', '!=', $ancillaryCost->id)
            ->where('date', '>', $ancillaryCost->date)
            ->whereHas('items', fn ($q) => $q->whereIn('product_id', $productIds))
            ->where('status', InvoiceAncillaryCostStatus::APPROVED);

        if ($query->exists()) {
            throw new Exception(__('Cannot change ancillary cost status because there are subsequent ancillary costs those are approved for the same invoice products. Please unapprove those ancillary costs first.'), 400);
        }
    }

    public static function getAllowedInvoicesForAncillaryCostsCreatingOrEditing(): Collection
    {
        $invoices = Invoice::where('invoice_type', InvoiceType::BUY)->orderBy('date')->get();

        return $invoices->reject(function ($invoice) {
            $productIds = $invoice->items->where('itemable_type', Product::class)->pluck('itemable_id')->toArray();

            return InvoiceService::notAllowedInvoiceForAncillaryCosts($invoice, $productIds);
        });
    }
}
