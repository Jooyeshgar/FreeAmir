<?php

namespace App\Services;

use App\Enums\InvoiceType;
use App\Models\Customer;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Service;

/**
 * Helper class to build document transactions from invoice data.
 * Follows separation of concerns by isolating transaction generation logic.
 *
 * Each invoice type (BUY, SELL, RETURN_BUY, RETURN_SELL) has its own
 * dedicated item-transaction builder so accounting rules are clear and isolated.
 */
class InvoiceTransactionBuilder
{
    private array $transactions = [];

    /**
     * Invoice items used to build transactions.
     *
     * Each item is an associative array with these keys:
     *
     * - `itemable_type` (string|null) : Type of the itemable. Expected values: `'product'`, `'service'` or null.
     * - `itemable_id` (int)          : The id of the product or service in the database. Required when `itemable_type` is set.
     * - `quantity` (int|float)       : Quantity of the item. Optional, defaults to `1` when omitted.
     * - `unit` (int|float)           : Unit price for the item (required for amount calculations).
     * - `unit_discount` (int|float)  : Discount amount applied to this item line (optional, default `0`). Note: this is treated as an absolute amount for the line.
     * - `vat` (int|float)           : VAT percentage (e.g. `9` for 9%), or VAT value when `vat_is_value` is true.
     * - `vat_is_value` (bool)       : When true, treat `vat` as a value instead of a percentage.
     *
     * Example:
     * ```php
     * [
     *   ['itemable_type' => 'product', 'itemable_id' => 123, 'quantity' => 2, 'unit' => 15000, 'unit_discount' => 1000, 'vat' => 9],
     *   ['itemable_type' => 'service', 'itemable_id' => 5, 'quantity' => 1, 'unit' => 30000],
     * ]
     * ```
     *
     * @var array<int, array<string, mixed>>
     */
    private array $items;

    private array $invoiceData;

    private float $totalDiscount = 0;

    private float $totalVat = 0;

    private float $totalAmount = 0;

    private float $subtractions = 0;

    private InvoiceType $invoiceType;

    public function __construct(array $items, array $invoiceData)
    {
        $this->items = $items;
        $this->invoiceData = $invoiceData;
        $this->invoiceType = $invoiceData['invoice_type'];
    }

    /**
     * Build all transactions for the invoice.
     *
     * @return array ['transactions' => array, 'totalVat' => float, 'totalDiscount' => float, 'totalAmount' => float, 'subtractions' => float]
     */
    public function build(): array
    {
        $this->transactions = [];
        $this->totalDiscount = 0;
        $this->totalVat = 0;
        $this->totalAmount = 0;
        $this->subtractions = floatval($this->invoiceData['subtraction'] ?? 0);

        match ($this->invoiceType) {
            InvoiceType::SELL => $this->buildSellItemsTransactions(),
            InvoiceType::BUY => $this->buildBuyItemsTransactions(),
            InvoiceType::RETURN_SELL => $this->buildReturnSellItemsTransactions(),
            InvoiceType::RETURN_BUY => $this->buildReturnBuyItemsTransactions(),
        };

        $this->buildDiscountTransaction();
        $this->buildVatTransaction();
        $this->buildSubtractionTransaction();
        $this->buildCustomerTransaction();

        return [
            'transactions' => $this->transactions,
            'totalVat' => $this->totalVat,
            'totalDiscount' => $this->totalDiscount,
            'totalAmount' => $this->totalAmount + $this->totalVat - $this->subtractions,
            'subtractions' => $this->subtractions,
        ];
    }

    // ──────────────────────────────────────────────
    //  Per-item helpers
    // ──────────────────────────────────────────────

    /**
     * Resolve product or service model from an item array.
     *
     * @return array{product: Product|null, service: Service|null}|null null when type is missing/unknown
     */
    private function resolveItemable(array $item): ?array
    {
        $type = $item['itemable_type'] ?? null;

        if (! $type) {
            return null;
        }

        $product = $type === 'product' ? Product::findOrFail($item['itemable_id']) : null;
        $service = $type === 'service' ? Service::findOrFail($item['itemable_id']) : null;

        if (! $product && ! $service) {
            return null;
        }

        return compact('product', 'service');
    }

    /**
     * Extract common numeric values from an item and accumulate totals.
     *
     * @return array{quantity: float, unitPrice: float, itemDiscount: float, itemVat: float, itemAmount: float}
     */
    private function extractItemValues(array $item): array
    {
        $quantity = $item['quantity'] ?? 1;
        $unitPrice = $item['unit'];
        $itemDiscount = $item['unit_discount'] ?? 0;
        $vatIsValue = $item['vat_is_value'] ?? false;
        $itemVat = $vatIsValue
            ? floatval($item['vat'] ?? 0)
            : (($item['vat'] ?? 0) / 100) * ($quantity * $unitPrice - $itemDiscount);
        $itemAmount = $quantity * $unitPrice;

        $this->totalDiscount += $itemDiscount;
        $this->totalVat += $itemVat;
        $this->totalAmount += $itemAmount - $itemDiscount;

        return compact('quantity', 'unitPrice', 'itemDiscount', 'itemVat', 'itemAmount');
    }

    /**
     * Build a standard description for a line item transaction.
     */
    private function itemDescription(float $quantity): string
    {
        return __('Invoice').' '.$this->invoiceType->label()
            .' '.__(' with number ').' '.formatNumber($this->invoiceData['number'])
            .' ('.formatNumber($quantity).' '.__('Number').')';
    }

    /**
     * Build a description for COGS or cost-of-services transactions.
     */
    private function cogsDescription(string $label): string
    {
        return $label.' '.__('Invoice').' '.$this->invoiceType->label()
            .' '.__(' with number ').' '.formatNumber($this->invoiceData['number']);
    }

    /**
     * Look up the cost-of-goods-after value from the original (returned) invoice item.
     *
     * For return invoices the cost must come from the original sale/purchase.
     * Falls back to the product's current average_cost when the original item cannot be found.
     */
    private function getReturnedItemCost(Product $product): float
    {
        $returnedInvoiceId = $this->invoiceData['returned_invoice_id'] ?? null;

        if ($returnedInvoiceId) {
            $returnedInvoiceItem = InvoiceItem::where('invoice_id', $returnedInvoiceId)
                ->where('itemable_type', Product::class)
                ->where('itemable_id', $product->id)
                ->first();

            if ($returnedInvoiceItem && $returnedInvoiceItem->cog_after > 0) {
                return $returnedInvoiceItem->cog_after;
            }
        }

        // Fall back to the product's current average cost when no reference invoice is found
        return $product->average_cost ?? 0;
    }

    // ──────────────────────────────────────────────
    //  SELL
    // ──────────────────────────────────────────────

    /**
     * Sell:
     *   Sales revenue  ← credit (positive)
     *   Inventory      ← credit (positive – decrease in stock)
     *   Cost of goods  ← debit  (negative)
     */
    private function buildSellItemsTransactions(): void
    {
        foreach ($this->items as $item) {
            $resolved = $this->resolveItemable($item);
            if (! $resolved) {
                continue;
            }

            ['product' => $product, 'service' => $service] = $resolved;
            $vals = $this->extractItemValues($item);
            $desc = $this->itemDescription($vals['quantity']);

            // Sales revenue (credit)
            $subjectId = $product ? $product->income_subject_id : $service->subject_id;
            $this->transactions[] = [
                'subject_id' => $subjectId,
                'desc' => $desc,
                'value' => $vals['itemAmount'],
            ];

            // Inventory and cost of goods (products only)
            if ($product) {
                $averageCost = $product->average_cost ?? 0;

                // Inventory ← credit (decrease)
                $this->transactions[] = [
                    'subject_id' => $product->inventory_subject_id,
                    'desc' => $desc,
                    'value' => $averageCost * $vals['quantity'],
                ];

                // Cost of goods sold ← debit
                $this->transactions[] = [
                    'subject_id' => $product->cogs_subject_id,
                    'desc' => $this->cogsDescription(__('Cost of Goods Sold')),
                    'value' => -$averageCost * $vals['quantity'],
                ];
            }
        }
    }

    // ──────────────────────────────────────────────
    //  BUY
    // ──────────────────────────────────────────────

    /**
     * Buy:
     *   Inventory / service expense ← debit (negative)
     *   Cost of services (only for pure service invoices with no products)
     */
    private function buildBuyItemsTransactions(): void
    {
        foreach ($this->items as $item) {
            $resolved = $this->resolveItemable($item);
            if (! $resolved) {
                continue;
            }

            ['product' => $product, 'service' => $service] = $resolved;
            $vals = $this->extractItemValues($item);
            $desc = $this->itemDescription($vals['quantity']);

            if ($product) {
                // Inventory ← debit
                $this->transactions[] = [
                    'subject_id' => $product->inventory_subject_id,
                    'desc' => $desc,
                    'value' => -($vals['unitPrice'] * $vals['quantity']),
                ];
            }

            if ($service) {
                // Cost of services (pure service invoice only)
                $this->transactions[] = [
                    'subject_id' => $service->cogs_subject_id,
                    'desc' => $this->cogsDescription(__('Cost of Services')),
                    'value' => -($vals['unitPrice'] * $vals['quantity']),
                ];
            }
        }
    }

    // ──────────────────────────────────────────────
    //  RETURN SELL
    // ──────────────────────────────────────────────

    /**
     * Return sell:
     *   Sales returns and allowances ← debit  (negative)
     *   Inventory                    ← debit  (negative – increase in stock)
     *   Cost of goods sold           ← credit (positive)
     */
    private function buildReturnSellItemsTransactions(): void
    {
        foreach ($this->items as $item) {
            $resolved = $this->resolveItemable($item);
            if (! $resolved) {
                continue;
            }

            ['product' => $product, 'service' => $service] = $resolved;
            $vals = $this->extractItemValues($item);
            $desc = $this->itemDescription($vals['quantity']);

            // Sales returns (debit)
            $subjectId = $product ? $product->sales_returns_subject_id : $service->sales_returns_subject_id;
            $this->transactions[] = [
                'subject_id' => $subjectId,
                'desc' => $desc,
                'value' => -$vals['itemAmount'],
            ];

            // Inventory and cost of goods (products only)
            if ($product) {
                $returnedCost = $this->getReturnedItemCost($product);

                // Inventory ← debit (increase)
                $this->transactions[] = [
                    'subject_id' => $product->inventory_subject_id,
                    'desc' => $desc,
                    'value' => -$returnedCost * $vals['quantity'],
                ];

                // Cost of goods sold ← credit (decrease)
                $this->transactions[] = [
                    'subject_id' => $product->cogs_subject_id,
                    'desc' => $this->cogsDescription(__('Cost of Goods Sold')),
                    'value' => $returnedCost * $vals['quantity'],
                ];
            }
        }
    }

    // ──────────────────────────────────────────────
    //  RETURN BUY
    // ──────────────────────────────────────────────

    /**
     * Return buy:
     *   Inventory    ← credit (positive – decrease in stock)
     *   Cost of services (only for pure service invoices with no products)
     */
    private function buildReturnBuyItemsTransactions(): void
    {
        $diff_cog_and_unitPrice = 0;
        $invoice_items_quantity_on_irrevocable_ancillary_costs = 0;
        foreach ($this->items as $item) {
            $resolved = $this->resolveItemable($item);
            if (! $resolved) {
                continue;
            }

            ['product' => $product, 'service' => $service] = $resolved;
            $vals = $this->extractItemValues($item);
            $desc = $this->itemDescription($vals['quantity']);

            if ($product) {
                $returnedCost = $this->getReturnedItemCost($product);
                if ($returnedCost !== $vals['unitPrice']) {
                    $diff_cog_and_unitPrice += abs($returnedCost - $vals['unitPrice']);
                    $invoice_items_quantity_on_irrevocable_ancillary_costs += $vals['quantity'];
                }

                // Inventory ← credit (decrease)
                $this->transactions[] = [
                    'subject_id' => $product->inventory_subject_id,
                    'desc' => $desc,
                    'value' => ($vals['unitPrice'] - $diff_cog_and_unitPrice) * $vals['quantity'],
                ];
            }

            if ($service) {
                // Cost of services (pure service invoice only)
                $this->transactions[] = [
                    'subject_id' => $service->cogs_subject_id,
                    'desc' => $this->cogsDescription(__('Cost of Services')),
                    'value' => $vals['unitPrice'] * $vals['quantity'],
                ];
            }
        }

        if ($diff_cog_and_unitPrice !== 0) {
            $this->transactions[] = [
                'subject_id' => config('amir.sundry_cost'),
                'desc' => __('Irrevocable ancillary cost of').' '.__('Invoice').' '.$this->invoiceType->label()
                    .' '.__(' with number ').' '.formatNumber($this->invoiceData['number']),
                'value' => $diff_cog_and_unitPrice * $invoice_items_quantity_on_irrevocable_ancillary_costs,
            ];
        }
    }

    // ──────────────────────────────────────────────
    //  Shared transaction builders
    // ──────────────────────────────────────────────

    /**
     * Create transaction for total discount if not zero.
     */
    private function buildDiscountTransaction(): void
    {
        if ($this->totalDiscount <= 0) {
            return;
        }

        $discountSubjectId = $this->invoiceType->isSell()
            ? config('amir.sell_discount')
            : config('amir.buy_discount');

        $value = match ($this->invoiceType) {
            InvoiceType::SELL => -$this->totalDiscount,
            InvoiceType::BUY => $this->totalDiscount,
            InvoiceType::RETURN_SELL => $this->totalDiscount,
            InvoiceType::RETURN_BUY => -$this->totalDiscount,
        };

        $this->transactions[] = [
            'subject_id' => $discountSubjectId,
            'desc' => $this->invoiceType->label().' '.__('Invoice discount with number').' '.formatNumber($this->invoiceData['number']),
            'value' => $value,
        ];
    }

    /**
     * Create transaction for total VAT/tax if not zero.
     */
    private function buildVatTransaction(): void
    {
        if ($this->totalVat <= 0) {
            return;
        }

        $vatSubjectId = $this->invoiceType->isSell()
            ? config('amir.sell_vat')
            : config('amir.buy_vat');

        $value = match ($this->invoiceType) {
            InvoiceType::SELL => $this->totalVat,
            InvoiceType::BUY => -$this->totalVat,
            InvoiceType::RETURN_SELL => -$this->totalVat,
            InvoiceType::RETURN_BUY => $this->totalVat,
        };

        $this->transactions[] = [
            'subject_id' => $vatSubjectId,
            'desc' => __('Invoice').' '.$this->invoiceType->label().' '.__(' with number ').' '.formatNumber($this->invoiceData['number']),
            'value' => $value,
        ];
    }

    /**
     * Create transaction for subtraction if not zero.
     */
    private function buildSubtractionTransaction(): void
    {
        if ($this->subtractions <= 0) {
            return;
        }

        $subtractionSubjectId = $this->invoiceType->isSell()
            ? config('amir.sell_discount')
            : config('amir.buy_discount');

        $value = match ($this->invoiceType) {
            InvoiceType::SELL => -$this->subtractions,
            InvoiceType::BUY => $this->subtractions,
            InvoiceType::RETURN_SELL => $this->subtractions,
            InvoiceType::RETURN_BUY => -$this->subtractions,
        };

        $this->transactions[] = [
            'subject_id' => $subtractionSubjectId,
            'desc' => $this->invoiceType->label().' '.__('Invoice subtraction with number').' '.formatNumber($this->invoiceData['number']),
            'value' => $value,
        ];
    }

    /**
     * Create transaction for customer with total amount to pay.
     */
    private function buildCustomerTransaction(): void
    {
        $customerId = $this->invoiceData['customer_id'];
        $cashPayment = floatval($this->invoiceData['cash_payment'] ?? 0);
        $customerTotal = $this->totalAmount - $this->subtractions - $cashPayment + $this->totalVat;

        $subjectId = Customer::find($customerId)->subject->id;

        $value = match ($this->invoiceType) {
            InvoiceType::SELL => -$customerTotal,
            InvoiceType::BUY => $customerTotal,
            InvoiceType::RETURN_SELL => $customerTotal,
            InvoiceType::RETURN_BUY => -$customerTotal,
        };

        $this->transactions[] = [
            'subject_id' => $subjectId,
            'desc' => __('Invoice').' '.$this->invoiceType->label().' '.__(' with number ').' '.formatNumber($this->invoiceData['number']),
            'value' => $value,
        ];
    }
}
