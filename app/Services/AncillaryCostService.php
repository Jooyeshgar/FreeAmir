<?php

namespace App\Services;

use App\Enums\AncillaryCostType;
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
    public static function createAncillaryCost(User $user, array $data): void
    {
        self::validateAncillaryCostData($data);

        $invoice = Invoice::findOrFail($data['invoice_id']);
        $type = AncillaryCostType::from($data['type']);

        $documentData = [
            'date' => $data['date'] ?? now()->toDateString(),
            'title' => $type->label().' '.__('Invoice #').($invoice->number ?? ''),
        ];

        $transactionBuilder = new AncillaryCostTransactionBuilder($data);
        $transactions = $transactionBuilder->build();

        DB::transaction(function () use ($documentData, $type, $user, $data, $invoice, $transactions) {
            $document = DocumentService::createDocument(
                $user,
                $documentData,
                $transactions
            );

            $ancillaryCost = AncillaryCost::create([
                'invoice_id' => $invoice->id,
                'company_id' => $data['company_id'],
                'date' => $data['date'] ?? now()->toDateString(),
                'type' => $type,
                'amount' => $data['amount'],
                'vat' => $data['vatPrice'] ?? 0,
                'document_id' => $document->id,
            ]);

            self::syncAncillaryCostItems($ancillaryCost, $data['ancillaryCosts'] ?? []);

            CostOfGoodsService::updateProductsAverageCost($invoice);
            self::syncCOGAfterAncillarityCost($invoice);
        });
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

    public static function updateAncillaryCost(AncillaryCost $ancillaryCost, array $data)
    {
        self::validateAncillaryCostData($data);

        DB::transaction(function () use ($ancillaryCost, $data) {

            $type = AncillaryCostType::from($data['type']);

            $documentData = [
                'date' => $data['date'],
                'title' => $type->label().' '.__('Invoice #').($invoice->number ?? ''),
            ];

            $transactionBuilder = new AncillaryCostTransactionBuilder($data);
            $transactions = $transactionBuilder->build();

            $document = $ancillaryCost->document;
            DocumentService::updateDocument($document, $documentData);
            DocumentService::updateDocumentTransactions($document->id, $transactions);

            $ancillaryCost->update([
                'invoice_id' => $data['invoice_id'],
                'date' => $data['date'],
                'type' => $type,
                'amount' => $data['amount'],
                'vat' => $data['vatPrice'] ?? 0,
            ]);

            $invoice = $ancillaryCost->invoice; // Invoice::findOrFail($data['invoice_id']);

            $data['date'] ??= now()->toDateString();

            self::syncAncillaryCostItems($ancillaryCost, $data['ancillaryCosts'] ?? []);

            CostOfGoodsService::updateProductsAverageCost($invoice);

            self::syncCOGAfterAncillarityCost($invoice);
        });
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

            DocumentService::deleteDocument($ancillaryCost->document_id);

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

    private static function checkAncillaryCostDeleteableOrEditable(AncillaryCost $ancillaryCost): void
    {
        if (! $ancillaryCost) {
            throw new Exception(__('Ancillary Cost not found'), 404);
        }

        $productIds = $ancillaryCost->items->pluck('product_id')->toArray();

        if (empty($productIds)) {
            return;
        }

        if (InvoiceService::hasSubsequentInvoicesForProduct($ancillaryCost->invoice, $productIds)) {
            throw new Exception(__('Ancillary Cost cannot be edited/deleted because there are subsequent buy/sell invoices after the respective invoice date.'), 400);
        }
    }

    public static function getAllowedInvoicesForAncillaryCostsCreatingOrEditing(): Collection
    {
        $invoices = Invoice::where('invoice_type', InvoiceType::BUY)->orderBy('date')->get();

        return $invoices->reject(function ($invoice) {
            $productIds = $invoice->items->where('itemable_type', Product::class)->pluck('itemable_id')->toArray();

            return InvoiceService::hasSubsequentInvoicesForProduct($invoice, $productIds);
        });
    }
}
