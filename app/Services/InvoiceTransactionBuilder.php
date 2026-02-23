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
     * @return array ['transactions' => array, 'totalVat' => float, 'totalDiscount' => float, 'totalAmount' => float]
     */
    public function build(): array
    {
        $this->transactions = [];
        $this->totalDiscount = 0;
        $this->totalVat = 0;
        $this->totalAmount = 0;

        $this->buildItemsTransactions();

        $this->buildDiscountTransaction();

        $this->buildVatTransaction();

        $this->buildSubtractionTransaction();

        $this->buildCustomerTransaction();

        $this->buildCogsTransaction();

        $this->buildCogsServiceTransaction();

        return [
            'transactions' => $this->transactions,
            'totalVat' => $this->totalVat,
            'totalDiscount' => $this->totalDiscount,
            'totalAmount' => $this->totalAmount + $this->totalVat - $this->subtractions,
            'subtractions' => $this->subtractions,
        ];
    }

    private function buildCogsServiceTransaction(): void
    {
        $isServiceBuy = $this->invoiceType->isBuy() && collect($this->items)->where('itemable_type', 'product')->isEmpty();

        if (! $isServiceBuy) {
            return;
        }

        foreach ($this->items as $item) {
            $type = $item['itemable_type'] ?? null;

            if ($type !== 'service') {
                continue;
            }

            $service = Service::find($item['itemable_id']) ?? null;

            $this->transactions[] = [
                'subject_id' => $service->cogs_subject_id,
                'desc' => __('Cost of Services').' '.__('Invoice').' '.$this->invoiceType->label().' '.__(' with number ').' '.formatNumber($this->invoiceData['number']),
                'value' => $this->invoiceType === InvoiceType::RETURN_BUY ? $item['unit'] : -$item['unit'],
            ];
        }
    }

    private function buildCogsTransaction(): void
    {
        if ($this->invoiceType->isBuy()) {
            return;
        }

        foreach ($this->items as $item) {
            $type = $item['itemable_type'] ?? null;

            if ($type !== 'product') {
                continue;
            }

            $product = Product::find($item['itemable_id']) ?? null;
            $averageCost = $product->average_cost ?? 0;

            $returned_invoice_id = $this->invoiceData['returned_invoice_id'] ?? null;
            $returnedInvoiceItem = $returned_invoice_id ? InvoiceItem::where('invoice_id', $returned_invoice_id)
                ->where('itemable_type', Product::class)->where('itemable_id', $product->id)->first() : null;

            // Use the COG after value from the returned invoice item if available, otherwise zero
            $returnedItemCogsCost = $returnedInvoiceItem?->cog_after ?? 0;

            $quantity = $item['quantity'] ?? 1;
            $this->transactions[] = [
                'subject_id' => $product->cogs_subject_id,
                'desc' => __('Cost of Goods Sold').' '.__('Invoice').' '.$this->invoiceType->label().' '.__(' with number ').' '.formatNumber($this->invoiceData['number']),
                'value' => $this->invoiceType === InvoiceType::RETURN_SELL ? $returnedItemCogsCost * $quantity : -$averageCost * $quantity,
            ];
        }
    }

    /**
     * Create a transaction for each invoice item.
     */
    private function buildItemsTransactions(): void
    {
        foreach ($this->items as $item) {

            $type = $item['itemable_type'] ?? null;
            if (! $type) {
                continue;
            }

            $product = $type === 'product' ? Product::findOrFail($item['itemable_id']) : null;
            $service = $type === 'service' ? Service::findOrFail($item['itemable_id']) : null;

            if (! $product && ! $service) {
                continue;
            }

            $quantity = $item['quantity'] ?? 1;
            $unitPrice = $item['unit'];
            $itemDiscount = $item['unit_discount'] ?? 0;
            $vatIsValue = $item['vat_is_value'] ?? false;
            $itemVat = $vatIsValue ? floatval($item['vat'] ?? 0) : (($item['vat'] ?? 0) / 100) * ($quantity * $unitPrice - $itemDiscount);
            $itemAmount = $quantity * $unitPrice;

            $this->totalDiscount += $itemDiscount;
            $this->totalVat += $itemVat;
            $this->totalAmount += $itemAmount - $itemDiscount;

            $desc = __('Invoice').' '.$this->invoiceType->label().' '.__(' with number ').' '.formatNumber($this->invoiceData['number']).' ('.formatNumber($quantity).' '.__('Number').')';

            if ($this->invoiceType === InvoiceType::SELL) {
                $subjectId = $product ? $product->income_subject_id : $service->subject_id;

                $this->transactions[] = [
                    'subject_id' => $subjectId,
                    'desc' => $desc,
                    'value' => $itemAmount,
                ];
            }

            if ($this->invoiceType === InvoiceType::RETURN_SELL) {
                $subjectId = $product ? $product->sales_returns_subject_id : $service->subject_id;

                $this->transactions[] = [
                    'subject_id' => $subjectId,
                    'desc' => $desc,
                    'value' => -$itemAmount,
                ];

                if ($product) {
                    $returned_invoice_id = $this->invoiceData['returned_invoice_id'] ?? null;
                    $returnedInvoiceItem = $returned_invoice_id ? InvoiceItem::where('invoice_id', $returned_invoice_id)
                        ->where('itemable_type', Product::class)->where('itemable_id', $product->id)->first() : null;

                    // Use the COG after value from the returned invoice item if available, otherwise zero
                    $returnedItemCogsCost = $returnedInvoiceItem?->cog_after ?? 0;

                    $this->transactions[] = [
                        'subject_id' => $product->inventory_subject_id,
                        'desc' => $desc,
                        'value' => -$returnedItemCogsCost * $quantity,
                    ];
                }
            } elseif ($this->invoiceType === InvoiceType::RETURN_BUY) {
                $subjectId = $product ? $product->inventory_subject_id : $service->subject_id;

                $this->transactions[] = [
                    'subject_id' => $subjectId,
                    'desc' => $desc,
                    'value' => $unitPrice * $quantity,
                ];
            }

            if (! $this->invoiceType->isReturn() && $product) {
                $inventoryValue = $this->invoiceType === InvoiceType::SELL ? $product->average_cost * $quantity : -($unitPrice * $quantity);

                $this->transactions[] = [
                    'subject_id' => $product->inventory_subject_id,
                    'desc' => $desc,
                    'value' => $inventoryValue,
                ];
            }
        }
    }

    /**
     * Create transaction for total discount if not zero.
     */
    private function buildDiscountTransaction(): void
    {
        if ($this->totalDiscount <= 0) {
            return;
        }

        $discountSubjectId = $this->invoiceType->isSell() ? config('amir.sell_discount') : config('amir.buy_discount');

        $value = match ($this->invoiceType) {
            InvoiceType::SELL => -$this->totalDiscount,
            InvoiceType::RETURN_SELL => $this->totalDiscount,
            default => $this->invoiceType->isReturn() ? -$this->totalDiscount : $this->totalDiscount,
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

        $vatSubjectId = $this->invoiceType->isSell() ? config('amir.sell_vat') : config('amir.buy_vat');

        $value = match ($this->invoiceType) {
            InvoiceType::SELL => $this->totalVat,
            InvoiceType::RETURN_SELL => -$this->totalVat,
            default => $this->invoiceType->isReturn() ? $this->totalVat : -$this->totalVat,
        };

        $this->transactions[] = [
            'subject_id' => $vatSubjectId,
            'desc' => __('Invoice').' '.$this->invoiceType->label().' '.__(' with number ').' '.formatNumber($this->invoiceData['number']),
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

        $subject_id = Customer::find($customerId)->subject->id;

        $value = match ($this->invoiceType) {
            InvoiceType::SELL => -$customerTotal,
            InvoiceType::RETURN_SELL => $customerTotal,
            default => $this->invoiceType->isReturn() ? -$customerTotal : $customerTotal,
        };

        $this->transactions[] = [
            'subject_id' => $subject_id,
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

        $this->subtractions = floatval($this->invoiceData['subtraction'] ?? 0);
        $subtractionSubjectId = $this->invoiceType->isSell() ? config('amir.sell_discount') : config('amir.buy_discount');

        $value = match ($this->invoiceType) {
            InvoiceType::SELL => -$this->subtractions,
            InvoiceType::RETURN_SELL => $this->subtractions,
            default => $this->invoiceType->isReturn() ? -$this->subtractions : $this->subtractions,
        };

        $this->transactions[] = [
            'subject_id' => $subtractionSubjectId,
            'desc' => $this->invoiceType->label().' '.__('Invoice subtraction with number').' '.formatNumber($this->invoiceData['number']),
            'value' => $value,
        ];
    }
}
