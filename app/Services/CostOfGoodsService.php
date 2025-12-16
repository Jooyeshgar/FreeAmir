<?php

namespace App\Services;

use App\Enums\InvoiceAncillaryCostStatus;
use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;

class CostOfGoodsService
{
    /**
     * Update the average cost of products based on invoice items and ancillary costs.
     *
     * This method calculates and updates the average cost for each product in an invoice by considering:
     * - The base cost from the invoice item (excluding VAT)
     * - Associated ancillary costs allocated to the product
     * - The previous cost of goods (COG) from the last invoice containing the product
     *
     * The average cost is calculated using the weighted average formula:
     * Average Cost = (Total Costs + Previous COG * Available Quantity) / (Available Quantity + New Quantity)
     *
     * @param  Invoice  $invoice  The invoice containing the items whose products need cost updates
     * @return void
     *
     * @throws \Exception May throw exceptions related to database operations during save
     */
    public static function updateProductsAverageCost(Invoice $invoice)
    {
        if ($invoice->invoice_type !== InvoiceType::BUY) {
            return;
        }

        foreach ($invoice->items as $invoiceItem) {
            $product = $invoiceItem->itemable;
            $previousInvoice = self::getPreviousInvoice($invoice, $product->id);

            $inputs = self::resolveCostCalculationInputs($invoice, $invoiceItem, $previousInvoice);
            if ($inputs === null) {
                $product->average_cost = 0;
                $product->save();

                continue;
            }

            $baseCost = $inputs['baseCost'];
            $availableQuantity = $inputs['availableQuantity'];
            $newQuantity = $inputs['newQuantity'];
            $ancillaryCosts = $inputs['ancillaryCosts'];

            $totalCosts = $baseCost;
            $totalCosts += self::sumApprovedAncillaryCostsForProduct($ancillaryCosts, $product->id);
            $totalCosts += self::sumPreviousCogContribution($invoice, $previousInvoice, $product->id, $availableQuantity);

            $denominator = $availableQuantity + $newQuantity;
            $product->average_cost = $denominator > 0 ? ($totalCosts / $denominator) : 0;
            $product->save();
        }
    }

    /**
     * @return array{baseCost: float, availableQuantity: float, newQuantity: float, ancillaryCosts: mixed}|null
     */
    private static function resolveCostCalculationInputs(Invoice $invoice, InvoiceItem $invoiceItem, ?Invoice $previousInvoice): ?array
    {
        // use current invoice item and invoice ancillary costs.
        if ($invoice->status->isApproved()) {
            return [
                'baseCost' => (float) $invoiceItem->amount - (float) ($invoiceItem->vat ?? 0),
                'availableQuantity' => (float) $invoiceItem->quantity_at,
                'newQuantity' => (float) $invoiceItem->quantity,
                'ancillaryCosts' => $invoice->ancillaryCosts,
            ];
        }

        // fall back to previous invoice snapshot; if none exists, caller resets avg cost.
        if (! $previousInvoice) {
            return null;
        }

        $previousInvoiceItem = $previousInvoice->items->where('itemable_id', $invoiceItem->itemable_id)->first();
        if (! $previousInvoiceItem) {
            return null;
        }

        return [
            'baseCost' => (float) $previousInvoiceItem->amount - (float) ($previousInvoiceItem->vat ?? 0),
            'availableQuantity' => (float) $previousInvoiceItem->quantity_at,
            'newQuantity' => (float) $previousInvoiceItem->quantity,
            'ancillaryCosts' => $previousInvoiceItem->ancillaryCosts,
        ];
    }

    private static function sumApprovedAncillaryCostsForProduct($ancillaryCosts, int $productId): float
    {
        if (is_null($ancillaryCosts)) {
            return 0.0;
        }

        $ancillaryCosts->loadMissing('items');

        // without VAT
        return (float) $ancillaryCosts->where('status', InvoiceAncillaryCostStatus::APPROVED)->flatMap->items->where('product_id', $productId)->sum('amount');
    }

    private static function sumPreviousCogContribution(Invoice $invoice, ?Invoice $previousInvoice, int $productId, float $availableQuantity): float
    {
        if (! $previousInvoice) {
            return 0.0;
        }

        // Approved calculation uses the immediate previous invoice item's COG.
        if ($invoice->status->isApproved()) {
            $previousInvoiceItem = $previousInvoice->items->where('itemable_id', $productId)->first();

            return $previousInvoiceItem ? (float) $previousInvoiceItem->cog_after * $availableQuantity : 0.0;
        }

        // Unapproved calculation uses the previous one of previous invoice item's COG.
        $previousPreviousInvoice = self::getPreviousInvoice($previousInvoice, $productId);
        if (! $previousPreviousInvoice) {
            return 0.0;
        }

        $previousPreviousInvoiceItem = $previousPreviousInvoice->items->where('itemable_id', $productId)->first();

        return $previousPreviousInvoiceItem ? (float) $previousPreviousInvoiceItem->cog_after * $availableQuantity : 0.0;
    }

    /**
     * Refresh product average cost after items are deleted from a buy invoice.
     *
     * This method resets the average cost of products to the cost from the previous buy invoice
     * before the given invoice date. It can handle both full invoice deletion and partial item deletion.
     *
     * @param  Invoice  $invoice  The invoice from which items were deleted
     * @param  array|null  $excludeItemIds  Optional array of item IDs to exclude (keep). If provided, only items NOT in this array will be processed.
     */
    public static function refreshProductCOGAfterItemsDeletion(Invoice $invoice, ?array $excludeItemIds = null): void
    {
        if ($invoice->invoice_type !== InvoiceType::BUY) {
            return;
        }

        // Get items to process based on whether we're excluding certain items
        $itemsToProcess = $excludeItemIds !== null
            ? $invoice->items()->whereNotIn('id', $excludeItemIds)->get()
            : $invoice->items;

        $productIds = $itemsToProcess->where('itemable_type', Product::class)->pluck('itemable_id')->toArray();

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

    /**
     * Calculate gross profit for a sale invoice item.
     */
    public static function calculateGrossProfit(InvoiceItem $invoiceItem)
    {
        $sellingPrice = (float) $invoiceItem->unit_price;
        $cost = (float) ($invoiceItem->cog_after ?? 0);
        $quantity = (float) $invoiceItem->quantity;

        return ($sellingPrice - $cost) * $quantity;
    }

    /**
     * Get the total cost value of current inventory for a product.
     */
    public static function getInventoryValue(Product $product)
    {
        return (float) $product->quantity * (float) ($product->average_cost ?? 0);
    }

    private static function getPreviousInvoice(Invoice $invoice, $productId)
    {
        return Invoice::where('number', '<', $invoice->number)
            ->where('status', InvoiceAncillaryCostStatus::APPROVED)
            ->where('invoice_type', $invoice->invoice_type)
            ->whereHas('items', fn ($query) => $query->where('itemable_id', $productId)
                                                                            && $query->where('itemable_type', Product::class))
            ->orderByDesc('number')->first();
    }

    private static function getNextInvoice(Invoice $invoice, $productId)
    {
        return Invoice::where('number', '>', $invoice->number)
            ->where('status', InvoiceAncillaryCostStatus::APPROVED)
            ->where('invoice_type', $invoice->invoice_type)
            ->whereHas('items', fn ($query) => $query->where('itemable_id', $productId)
                                                                            && $query->where('itemable_type', Product::class))
            ->orderByDesc('number')->first();
    }
}
