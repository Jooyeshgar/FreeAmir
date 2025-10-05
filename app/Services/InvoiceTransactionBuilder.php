<?php

namespace App\Services;

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

    public function __construct(array $items, array $invoiceData)
    {
        $this->items = $items;
        $this->invoiceData = $invoiceData;
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

        $this->buildCustomerTransaction();

        $this->buildCashPaymentTransaction();

        $this->buildAdditionTransaction();

        $this->buildSubtractionTransaction();

        return [
            'transactions' => $this->transactions,
            'totalVat' => $this->totalVat,
            'totalDiscount' => $this->totalDiscount,
            'totalAmount' => $this->totalAmount,
        ];
    }

    /**
     * Create a transaction for each invoice item.
     */
    private function buildItemTransactions(): void
    {
        $isSell = $this->invoiceData['is_sell'] ?? true;

        foreach ($this->items as $item) {
            $product = Product::find($item['product_id']);
            if (!$product) {
                continue;
            }

            $quantity = $item['quantity'] ?? 1;
            $unitPrice = $isSell ? $product->selling_price : $product->purchace_price;
            $unitDiscount = $item['unit_discount'] ?? 0;
            
            $itemDiscount = $isSell ? ($unitDiscount * $quantity) : 0;
            
            $vatRate = ($product->vat ?? $product->productGroup->vat ?? 0) / 100;
            $itemVat = $vatRate * ($quantity * $unitPrice - $itemDiscount);
            
            $itemAmount = $quantity * $unitPrice - $itemDiscount;

            $this->totalDiscount += $itemDiscount;
            $this->totalVat += $itemVat;
            $this->totalAmount += $itemAmount;

            $this->transactions[] = [
                'subject_id' => $product->subject_id,
                'desc' => $item['description'] ?? $product->name,
                'value' => $isSell ? $itemAmount : -$itemAmount,
            ];
        }
    }

    /**
     * Create transaction for total discount if not zero.
     */
    private function buildDiscountTransaction(): void
    {
        if ($this->totalDiscount > 0) {
            $isSell = $this->invoiceData['is_sell'] ?? true;
            $discountSubjectId = $isSell ? config('amir.sell_discount') : config('amir.buy_discount');
            
            $this->transactions[] = [
                'subject_id' => $discountSubjectId,
                'desc' => __('Invoice discount'),
                'value' => $isSell ? -$this->totalDiscount : $this->totalDiscount,
            ];
        }
    }

    /**
     * Create transaction for total VAT/tax if not zero.
     */
    private function buildVatTransaction(): void
    {
        if ($this->totalVat > 0) {
            $isSell = $this->invoiceData['is_sell'] ?? true;
            $vatSubjectId = $isSell ? config('amir.sell_vat') : config('amir.buy_vat');
            
            $this->transactions[] = [
                'subject_id' => $vatSubjectId,
                'desc' => __('Invoice VAT/Tax'),
                'value' => $isSell ? $this->totalVat : -$this->totalVat,
            ];
        }
    }

    /**
     * Create transaction for customer with total amount to pay.
     */
    private function buildCustomerTransaction(): void
    {
        $customerId = $this->invoiceData['customer_id'] ?? null;
        if (!$customerId) {
            return;
        }

        $addition = floatval($this->invoiceData['addition'] ?? 0);
        $subtraction = floatval($this->invoiceData['subtraction'] ?? 0);
        $cashPayment = floatval($this->invoiceData['cash_payment'] ?? 0);

        $customerTotal = $this->totalAmount + $this->totalVat + $addition - $subtraction - $cashPayment;

        $isSell = $this->invoiceData['is_sell'] ?? true;
        $this->transactions[] = [
            'subject_id' => $customerId,
            'desc' => __('Customer total'),
            'value' => $isSell ? -$customerTotal : $customerTotal,
        ];
    }

    /**
     * Create transaction for subtraction if not zero.
     */
    private function buildSubtractionTransaction(): void
    {
        $subtraction = floatval($this->invoiceData['subtraction'] ?? 0);
        
        if ($subtraction > 0) {
            $isSell = $this->invoiceData['is_sell'] ?? true;
            $subtractionSubjectId = $isSell ? config('amir.sell_discount') : config('amir.buy_discount');

            $this->transactions[] = [
                'subject_id' => $subtractionSubjectId,
                'desc' => $this->invoiceData['subtraction_desc'] ?? __('Deductions'),
                'value' => $isSell ? -$subtraction : $subtraction,
            ];
        }
    }
}
