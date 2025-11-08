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

        $this->buildItemTransactions();

        $this->buildDiscountTransaction();

        $this->buildVatTransaction();

        $this->buildSubtractionTransaction();

        $this->buildCustomerTransaction();

        return [
            'transactions' => $this->transactions,
            'totalVat' => $this->totalVat,
            'totalDiscount' => $this->totalDiscount,
            'totalAmount' => $this->totalAmount,
            'subtractions' => $this->subtractions,
        ];
    }

    /**
     * Create a transaction for each invoice item.
     */
    private function buildItemTransactions(): void
    {
        foreach ($this->items as $item) {
            $product = Product::find($item['product_id']);
            $service = Service::find($item['service_id']);
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
            $this->totalAmount += $itemAmount - $itemDiscount + $itemVat;

            $this->transactions[] = [
                'subject_id' => $product->inventory_subject_id ?? $service->subject_id,
                'desc' => $item['description'] ?? $product->name ?? $service->name,
                'value' => $this->invoiceType->isSell() ? $itemAmount : -$itemAmount,
            ];
        }
    }

    /**
     * Create transaction for total discount if not zero.
     */
    private function buildDiscountTransaction(): void
    {
        if ($this->totalDiscount > 0) {

            $discountSubjectId = $this->invoiceType->isSell() ? config('amir.sell_discount') : config('amir.buy_discount');

            $this->transactions[] = [
                'subject_id' => $discountSubjectId,
                'desc' => __('Invoice discount'),
                'value' => $this->invoiceType->isSell() ? -$this->totalDiscount : $this->totalDiscount,
            ];
        }
    }

    /**
     * Create transaction for total VAT/tax if not zero.
     */
    private function buildVatTransaction(): void
    {
        if ($this->totalVat > 0) {

            $vatSubjectId = $this->invoiceType->isSell() ? config('amir.sell_vat') : config('amir.buy_vat');

            $this->transactions[] = [
                'subject_id' => $vatSubjectId,
                'desc' => __('Invoice VAT/Tax'),
                'value' => $this->invoiceType->isSell() ? $this->totalVat : -$this->totalVat,
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

        $customerTotal = $this->totalAmount - $this->subtractions - $cashPayment;

        $subject_id = Customer::find($customerId)->subject->id;
        $this->transactions[] = [
            'subject_id' => $subject_id,
            'desc' => __('Customer total'),
            'value' => $this->invoiceType->isSell() ? -$customerTotal : $customerTotal,
        ];
    }

    /**
     * Create transaction for subtraction if not zero.
     */
    private function buildSubtractionTransaction(): void
    {
        $this->subtractions = floatval($this->invoiceData['subtraction'] ?? 0);

        if ($this->subtractions > 0) {

            $subtractionSubjectId = $this->invoiceType->isSell() ? config('amir.sell_discount') : config('amir.buy_discount');

            $this->transactions[] = [
                'subject_id' => $subtractionSubjectId,
                'desc' => $this->invoiceData['subtraction_desc'] ?? __('Deductions'),
                'value' => $this->invoiceType->isSell() ? -$this->subtractions : $this->subtractions,
            ];
        }
    }
}
