<?php

namespace App\Services;

use App\Enums\AncillaryCostType;
use App\Enums\InvoiceType;
use App\Models\AncillaryCost;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
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

        if (! $invoice->invoice_type === InvoiceType::BUY) {
            throw new \Exception(__('Ancillary costs can only be added to buy invoices'));
        }

        // Prepare document data
        $documentData = [
            'date' => $data['date'] ?? now()->toDateString(),
            'title' => (__('Ancillary Cost').' '.($invoice->number ?? '')),
            'number' => Document::max('number') + 1,
            'creator_id' => $user->id,
            'company_id' => session('active-company-id'),
        ];

        $transactionBuilder = new AncillaryCostTransactionBuilder($data);
        $transactions = $transactionBuilder->build();

        DB::transaction(function () use ($documentData, $data, $invoice, $transactions) {
            $document = DocumentService::createDocument(
                Auth::user(),
                $documentData,
                $transactions
            );

            $ancillaryCost = AncillaryCost::create([
                'invoice_id' => $invoice->id,
                'company_id' => $data['company_id'],
                'date' => $data['date'] ?? now()->toDateString(),
                'type' => AncillaryCostType::from($data['type']),
                'amount' => $data['amount'],
                'vat' => $data['vatPrice'] ?? 0,
                'document_id' => $document->id,
            ]);

            self::syncAncillaryCostItems($ancillaryCost, $data['ancillaryCosts'] ?? []);

            $ancillaryCost->loadMissing(['items.product', 'invoice.items.product']);

            CostOfGoodsService::updateProductAverageCostOnAddingAncillaryCost($ancillaryCost);
        });
    }

    public static function updateAncillaryCost(User $user, AncillaryCost $ancillaryCost, array $data)
    {
        self::validateAncillaryCostData($data);

        $invoice = Invoice::findOrFail($data['invoice_id']);

        if (! $invoice->invoice_type === InvoiceType::BUY) {
            throw new \Exception(__('Ancillary costs can only be added to buy invoices'));
        }

        // Prepare document data
        $documentData = [
            'date' => $data['date'] ?? now()->toDateString(),
            'title' => (__('Ancillary Cost').' '.($invoice->number ?? '')),
            'number' => $ancillaryCost->document->number,
            'creator_id' => $user->id,
            'company_id' => session('active-company-id'),
        ];

        $transactionBuilder = new AncillaryCostTransactionBuilder($data);
        $transactions = $transactionBuilder->build();

        DB::transaction(function () use ($ancillaryCost, $data, $documentData, $transactions) {

            $ancillaryCost->document->transactions()->delete();
            $ancillaryCost->document()->delete();

            $document = DocumentService::createDocument(
                Auth::user(),
                $documentData,
                $transactions
            );

            CostOfGoodsService::reverseUpdateProductAverageCostForAncillaryCost($ancillaryCost);

            $ancillaryCost->items()->delete();

            $ancillaryCost->update([
                'document_id' => $document->id,
                'invoice_id' => $ancillaryCost->invoice_id,
                'company_id' => $data['company_id'],
                'date' => $data['date'] ?? now()->toDateString(),
                'type' => AncillaryCostType::from($data['type']),
                'amount' => $data['amount'],
                'vat' => $data['vatPrice'] ?? 0,
            ]);

            self::syncAncillaryCostItems($ancillaryCost, $data['ancillaryCosts'] ?? []);

            CostOfGoodsService::updateProductAverageCostOnAddingAncillaryCost($ancillaryCost);
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
            CostOfGoodsService::reverseUpdateProductAverageCostForAncillaryCost($ancillaryCost);

            DocumentService::deleteDocument($ancillaryCost->document_id);

            $ancillaryCost->items()->delete();

            $ancillaryCost->delete();
        });
    }

    private static function syncAncillaryCostItems(AncillaryCost $ancillaryCost, array $items): void
    {
        if (empty($items)) {
            return;
        }

        $ancillaryCostItems = collect($items)->map(function (array $item) use ($ancillaryCost) {
            return [
                'company_id' => $ancillaryCost->company_id,
                'product_id' => $item['product_id'],
                'type' => $ancillaryCost->type,
                'amount' => $item['amount'],
            ];
        })->all();

        $ancillaryCost->items()->createMany($ancillaryCostItems);
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
}
