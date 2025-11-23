<?php

namespace App\Services;

use App\Enums\InvoiceType;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Service;

/**
 * Helper class to build document transactions from invoice data.
 * Follows separation of concerns by isolating transaction generation logic.
 */
class InvoiceTransactionBuilder
{
    private array $transactions = [];

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

        return [
            'transactions' => $this->transactions,
            'totalVat' => $this->totalVat,
            'totalDiscount' => $this->totalDiscount,
            'totalAmount' => $this->totalAmount,
            'subtractions' => $this->subtractions,
        ];
    }

    private function buildCogsTransaction(): void
    {
        if ($this->invoiceType === InvoiceType::BUY) {
            return;
        }

        foreach ($this->items as $item) {
            $type = $item['itemable_type'] ?? null;

            if ($type !== 'product') {
                continue;
            }

            $product = Product::find($item['itemable_id']) ?? null;
            $averageCost = $product->average_cost ?? 0;
            $quantity = $item['quantity'] ?? 1;

            $invoiceType = $this->invoiceType === InvoiceType::SELL ? __('Sell ') : __('Buy ');

            $this->transactions[] = [
                'subject_id' => $product->cogs_subject_id,
                'desc' => __('Cost of Goods Sold').' '.__('Invoice').' '.$this->invoiceType->label().__(' with number ').' '.formatNumber($this->invoiceData['number']),
                'value' => -$averageCost * $quantity,
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

            if ($type == null) {
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
            $vatRate = ($item['vat'] ?? 0) / 100;
            $itemVat = $vatRate * ($quantity * $unitPrice - $itemDiscount);
            $itemAmount = $quantity * $unitPrice;

            $this->totalDiscount += $itemDiscount;
            $this->totalVat += $itemVat;
            $this->totalAmount += $itemAmount - $itemDiscount;

            $invoiceType = $this->invoiceType === InvoiceType::SELL ? __('Sell') : __('Buy');

            if ($invoiceType === __('Sell')) {
                $subjectId = $product ? $product->income_subject_id : $service->subject_id;

                $this->transactions[] = [
                    'subject_id' => $subjectId,
                    'desc' => __('Invoice').' '.$invoiceType.' '.__(' with number ').' '.formatNumber($this->invoiceData['number']).' ('.formatNumber($quantity).' '.__('Number').')',
                    'value' => $itemAmount,
                ];
            }

            if ($type === 'product') {
                $this->transactions[] = [
                    'subject_id' => $product->inventory_subject_id,
                    'desc' => __('Invoice').' '.$invoiceType.' '.__(' with number ').' '.formatNumber($this->invoiceData['number']).' ('.formatNumber($quantity).' '.__('Number').')',
                    'value' => $this->invoiceType === InvoiceType::SELL ? ($product->average_cost * $quantity) : -($unitPrice * $quantity),
                ];
            }
        }
    }

    /**
     * Create transaction for total discount if not zero.
     */
    private function buildDiscountTransaction(): void
    {
        if ($this->totalDiscount > 0) {

            $discountSubjectId = $this->invoiceType === InvoiceType::SELL ? config('amir.sell_discount') : config('amir.buy_discount');

            $invoiceType = ($this->invoiceType === InvoiceType::SELL) ? __('Sell ') : __('Buy ');

            $this->transactions[] = [
                'subject_id' => $discountSubjectId,
                'desc' => $invoiceType.' '.__('Invoice discount with number').' '.formatNumber($this->invoiceData['number']),
                'value' => $this->invoiceType === InvoiceType::SELL ? -$this->totalDiscount : $this->totalDiscount,
            ];
        }
    }

    /**
     * Create transaction for total VAT/tax if not zero.
     */
    private function buildVatTransaction(): void
    {
        if ($this->totalVat > 0) {

            $vatSubjectId = $this->invoiceType === InvoiceType::SELL ? config('amir.sell_vat') : config('amir.buy_vat');

            $invoiceType = $this->invoiceType === InvoiceType::SELL ? __('Sell') : __('Buy');

            $this->transactions[] = [
                'subject_id' => $vatSubjectId,
                'desc' => __('Invoice').' '.$invoiceType.' '.__(' with number ').' '.formatNumber($this->invoiceData['number']),
                'value' => $this->invoiceType === InvoiceType::SELL ? $this->totalVat : -$this->totalVat,
            ];
        }
    }

    /**
     * Create transaction for customer with total amount to pay.
     */
    private function buildCustomerTransaction(): void
    {
        $customerId = $this->invoiceData['customer_id'];

        $cashPayment = floatval($this->invoiceData['cash_payment'] ?? 0);

        $customerTotal = $this->totalAmount - $this->subtractions - $cashPayment + $this->totalVat;

        $invoiceType = $this->invoiceType === InvoiceType::SELL ? __('Sell') : __('Buy');

        $subject_id = Customer::find($customerId)->subject->id;
        $this->transactions[] = [
            'subject_id' => $subject_id,
            'desc' => __('Invoice').' '.$invoiceType.' '.__(' with number ').' '.formatNumber($this->invoiceData['number']),
            'value' => $this->invoiceType === InvoiceType::SELL ? -$customerTotal : $customerTotal,
        ];
    }

    /**
     * Create transaction for subtraction if not zero.
     */
    private function buildSubtractionTransaction(): void
    {
        $this->subtractions = floatval($this->invoiceData['subtraction'] ?? 0);

        if ($this->subtractions > 0) {

            $subtractionSubjectId = $this->invoiceType === InvoiceType::SELL ? config('amir.sell_discount') : config('amir.buy_discount');

            $invoiceType = $this->invoiceType === InvoiceType::SELL ? __('Sell') : __('Buy');

            $this->transactions[] = [
                'subject_id' => $subtractionSubjectId,
                'desc' => $invoiceType.' '.__('Invoice subtraction with number').' '.formatNumber($this->invoiceData['number']),
                'value' => $this->invoiceType === InvoiceType::SELL ? -$this->subtractions : $this->subtractions,
            ];
        }
    }
}
