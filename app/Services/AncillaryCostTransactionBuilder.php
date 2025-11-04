<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;

/**
 * Helper class to build document transactions from ancillary cost data.
 * Follows separation of concerns by isolating transaction generation logic.
 */
class AncillaryCostTransactionBuilder
{
    private array $transactions = [];

    private array $data;

    private array $ancillaryCost;

    public function __construct(array $data)
    {
        $this->data = $data;
        $this->ancillaryCost = $data['ancillaryCosts'] ?? [];
    }

    /**
     * Build all transactions for the ancillary cost.
     */
    public function build(): array
    {
        $this->transactions = [];

        $this->buildAncillaryCostTransaction();

        $this->buildVatTransaction();

        $this->buildCustomerTransaction();

        return $this->transactions;
    }

    /**
     * Create a transaction for each ancillary cost item.
     */
    private function buildAncillaryCostTransaction(): void
    {
        foreach ($this->ancillaryCost as $item) {
            $product = Product::find($item['product_id']);

            $this->transactions[] = [
                'subject_id' => $product->inventory_subject_id,
                'desc' => __('Ancillary Cost for :item', ['item' => $product->name]),
                'credit' => 0,
                'debit' => $item['amount'],
            ];
        }
    }

    /**
     * Create transaction for total VAT/tax if not zero.
     */
    private function buildVatTransaction(): void
    {
        if ($this->data['vatPrice'] > 0) {
            $this->transactions[] = [
                'subject_id' => config('amir.buy_vat'),
                'desc' => __('Ancillary Cost VAT/Tax'),
                'credit' => 0,
                'debit' => $this->data['vatPrice'],
            ];
        }
    }

    /**
     * Create transaction for customer with total amount to pay.
     */
    private function buildCustomerTransaction(): void
    {
        $invoice = Invoice::find($this->data['invoice_id']);
        $customerId = $invoice->customer_id;
        $subject_id = Customer::find($customerId)->subject->id;

        $this->transactions[] = [
            'subject_id' => $subject_id,
            'desc' => __('Customer total'),
            'credit' => $this->data['amount'],
            'debit' => 0,
        ];
    }
}
