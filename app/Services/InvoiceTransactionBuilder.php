<?php

namespace App\Services;

use App\Enums\InvoiceType;
use App\Models\Customer;
use App\Models\Product;

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

        // $this->buildCostOfGoodsTransaction();

        // $this->buildInventoryTransaction();

        // $this->buildReturnSaleTransaction();

        // $this->buildIncomeTransaction();

        return [
            'transactions' => $this->transactions,
            'totalVat' => $this->totalVat,
            'totalDiscount' => $this->totalDiscount,
            'totalAmount' => $this->totalAmount,
            'subtractions' => $this->subtractions,
        ];
    }

    private function buildCostOfGoodsTransaction(): void
    {

        $this->transactions[] = [
            'subject_id' => config('amir.cost_of_goods'),
            'desc' => __('Cost of Goods Sold'),
            'credit' => $this->totalAmount,
            'debit' => 0,
        ];
    }

    private function buildInventoryTransaction(): void
    {
        $this->transactions[] = [
            'subject_id' => config('amir.product'),
            'desc' => __('Inventory'),
            'credit' => $this->totalAmount,
            'debit' => 0,
        ];
    }

    private function buildReturnSaleTransaction(): void
    {
        $this->transactions[] = [
            'subject_id' => config('amir.return_sale'),
            'desc' => __('Return Sale'),
            'credit' => 0,
            'debit' => $this->totalAmount,
        ];
    }

    private function buildIncomeTransaction(): void
    {
        $this->transactions[] = [
            'subject_id' => config('amir.income'),
            'desc' => __('Income'),
            'credit' => $this->totalAmount,
            'debit' => 0,
        ];
    }

    /**
     * Create a transaction for each invoice item.
     */
    private function buildItemTransactions(): void
    {
        foreach ($this->items as $item) {
            $product = Product::find($item['product_id']);
            if (! $product) {
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
                'subject_id' => $product->inventory_subject_id,
                'desc' => $item['description'] ?? $product->name,
                'credit' => $this->invoiceType->isSell() ? $itemAmount : 0,
                'debit' => $this->invoiceType->isSell() ? 0 : $itemAmount,
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
                'credit' => $this->invoiceType->isSell() ? 0 : $this->totalDiscount,
                'debit' => $this->invoiceType->isSell() ? $this->totalDiscount : 0,
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
                'credit' => $this->invoiceType->isSell() ? 0 : $this->totalVat,
                'debit' => $this->invoiceType->isSell() ? $this->totalVat : 0,
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
            'credit' => $this->invoiceType->isSell() ? 0 : $customerTotal,
            'debit' => $this->invoiceType->isSell() ? $customerTotal : 0,
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
                'credit' => $this->invoiceType->isSell() ? 0 : $this->subtractions,
                'debit' => $this->invoiceType->isSell() ? $this->subtractions : 0,
            ];
        }
    }
}
