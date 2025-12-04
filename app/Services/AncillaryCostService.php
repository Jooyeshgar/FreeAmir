<?php

namespace App\Services;

use App\Enums\AncillaryCostType;
use App\Enums\InvoiceAndAncillaryCostStatus;
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
    public static function createAncillaryCost(User $user, array $data, bool $approve = false): void
    {
        self::validateAncillaryCostData($data);

        $date = $data['date'] ?? now()->toDateString();
        $buildResult = (new AncillaryCostTransactionBuilder($data))->build();

        DB::transaction(function () use ($user, $data, $approve, $date, $buildResult) {
            $invoice = Invoice::findOrFail($data['invoice_id']);

            $ancillaryCost = AncillaryCost::create([
                'date' => $date,
                'invoice_id' => $invoice->id,
                'customer_id' => $data['customer_id'],
                'company_id' => $data['company_id'],
                'type' => AncillaryCostType::from($data['type']),
                'amount' => $data['amount'],
                'vat' => $data['vatPrice'] ?? 0,
                'status' => $approve ? InvoiceAndAncillaryCostStatus::APPROVED : InvoiceAndAncillaryCostStatus::PENDING,
            ]);

            if ($approve) {
                $document = DocumentService::createDocument($user, [
                    'date' => $date,
                    'title' => $data['title'] ?? (__('Ancillary Cost #').($data['number'] ?? '')),
                    'approved_at' => now(),
                    'approver_id' => $user->id,
                ], $buildResult['transactions']);

                $ancillaryCost->update(['document_id' => $document->id]);
                DocumentService::syncDocumentable($document, $ancillaryCost);
            }

            self::syncAncillaryCostItems($ancillaryCost, $data['ancillaryCosts'] ?? []);

            if ($approve) {
                self::updateCostOfGoods($invoice);
            }
        });
    }

    private static function updateCostOfGoods(Invoice $invoice): void
    {
        CostOfGoodsService::updateProductsAverageCost($invoice);

        foreach ($invoice->items as $item) {
            $item->update(['cog_after' => $item->itemable->average_cost]);
        }
    }

    public static function updateAncillaryCost(AncillaryCost $ancillaryCost, array $data, bool $approve = false): void
    {
        self::validateAncillaryCostData($data);
        self::checkAncillaryCostDeleteableOrEditable($ancillaryCost);

        $data['date'] ??= now()->toDateString();
        $buildResult = (new AncillaryCostTransactionBuilder($data))->build();

        DB::transaction(function () use ($ancillaryCost, $data, $approve, $buildResult) {
            $ancillaryCost->update([
                'invoice_id' => $data['invoice_id'],
                'customer_id' => $data['customer_id'],
                'date' => $data['date'],
                'type' => AncillaryCostType::from($data['type']),
                'amount' => $data['amount'],
                'vat' => $data['vatPrice'] ?? 0,
                'status' => $approve ? InvoiceAndAncillaryCostStatus::APPROVED : ($ancillaryCost->status ?? InvoiceAndAncillaryCostStatus::PENDING),
            ]);

            if ($approve) {
                $documentData = [
                    'date' => $data['date'],
                    'title' => $data['title'] ?? (__('Ancillary Cost #').($data['number'] ?? '')),
                    'approved_at' => now(),
                    'approver_id' => auth()->id(),
                ];

                if ($ancillaryCost->document) {
                    $document = DocumentService::updateDocument($ancillaryCost->document, $documentData);
                    DocumentService::updateDocumentTransactions($document->id, $buildResult['transactions']);
                } else {
                    $document = self::createDocumentFromAncillaryCostItems(auth()->user(), $ancillaryCost);
                }

                $ancillaryCost->update(['document_id' => $document->id]);
                DocumentService::syncDocumentable($document, $ancillaryCost);
            }

            self::syncAncillaryCostItems($ancillaryCost, $data['ancillaryCosts'] ?? []);

            if ($approve) {
                $ancillaryCost->refresh();
                self::updateCostOfGoods($ancillaryCost->invoice);
            }
        });
    }

    /**
     * Delete an ancillary cost and reverse its distribution.
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

            self::updateCostOfGoods($invoice);
        });
    }

    private static function syncAncillaryCostItems(AncillaryCost $ancillaryCost, array $items): void
    {
        $itemIds = collect($items)->map(fn ($item) => $ancillaryCost->items()->updateOrCreate(
            ['product_id' => $item['product_id']],
            ['type' => $ancillaryCost->type, 'amount' => $item['amount']]
        )->id);

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
        if ($ancillaryCost->status === InvoiceAndAncillaryCostStatus::APPROVED) {
            throw new Exception(__('Ancillary Cost is approved and cannot be edited or deleted. Please unapprove it first.'), 400);
        }

        $productIds = $ancillaryCost->items->pluck('product_id')->toArray();

        if (empty($productIds)) {
            return;
        }

        if (InvoiceService::hasSubsequentInvoicesNotApprovedStatusForProduct($ancillaryCost->invoice, $productIds)) {
            throw new Exception(__('Ancillary Cost cannot be edited/deleted because there are subsequent buy/sell invoices after the respective invoice date.'), 400);
        }
    }

    public static function getAllowedInvoicesForAncillaryCostsCreatingOrEditing(): Collection
    {
        return Invoice::where('invoice_type', InvoiceType::BUY)
            ->where('status', InvoiceAndAncillaryCostStatus::APPROVED)
            ->orderBy('date')
            ->get()
            ->reject(fn ($invoice) => InvoiceService::hasSubsequentInvoicesNotApprovedStatusForProduct(
                $invoice,
                $invoice->items->where('itemable_type', Product::class)->pluck('itemable_id')->toArray()
            ));
    }

    public function changeAncillaryCostStatus(AncillaryCost $ancillaryCost, string $status): void
    {
        DB::transaction(function () use ($ancillaryCost, $status) {
            match ($status) {
                'approve' => $this->approveAncillaryCost($ancillaryCost),
                'unapprove' => $this->unapproveAncillaryCost($ancillaryCost),
                default => null,
            };

            $ancillaryCost->save();
        });
    }

    private function approveAncillaryCost(AncillaryCost $ancillaryCost): void
    {
        $ancillaryCost->status = InvoiceAndAncillaryCostStatus::APPROVED;
        $document = self::createDocumentFromAncillaryCostItems(auth()->user(), $ancillaryCost);
        $ancillaryCost->document_id = $document->id;
        DocumentService::syncDocumentable($document, $ancillaryCost);
        self::updateCostOfGoods($ancillaryCost->invoice);
    }

    private function unapproveAncillaryCost(AncillaryCost $ancillaryCost): void
    {
        $ancillaryCost->status = InvoiceAndAncillaryCostStatus::UNAPPROVED;

        if ($ancillaryCost->document) {
            DocumentService::deleteDocument($ancillaryCost->document_id);
            $ancillaryCost->document_id = null;
        }
        // revert the invoice items without ancillary costs and average cost of products
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
            'date' => now()->toDateString(),
            'title' => $ancillaryCost->title ?? (__('Ancillary Cost #').($ancillaryCost->number ?? '')),
            'approved_at' => now(),
            'approver_id' => $user->id,
        ], $transactions);

        DocumentService::syncDocumentable($document, $ancillaryCost);

        return $document;
    }
}
