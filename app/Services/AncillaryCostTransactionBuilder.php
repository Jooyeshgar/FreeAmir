<?php

namespace App\Services;

use App\Models\AncillaryCost;
use App\Models\Customer;

/**
 * Helper class to build document transactions from invoice data.
 * Follows separation of concerns by isolating transaction generation logic.
 */
class AncillaryCostTransactionBuilder
{
    private array $transactions = [];

    private AncillaryCost $ancillaryCost;

    public function __construct(AncillaryCost $ancillaryCost)
    {
        $this->ancillaryCost = $ancillaryCost;
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
        $this->transactions[] = [
            'subject_id' => config('amir.inventory'),
            'desc' => __('Ancillary Cost'),
            'value' => $this->ancillaryCost->amount - $this->ancillaryCost->vat,
        ];
    }

    /**
     * Create transaction for total VAT/tax if not zero.
     */
    private function buildVatTransaction(): void
    {
        if ($this->ancillaryCost->vat > 0) {
            $this->transactions[] = [
                'subject_id' => config('amir.buy_vat'),
                'desc' => __('Ancillary Cost VAT/Tax'),
                'value' => $this->ancillaryCost->vat,
            ];
        }
    }

    /**
     * Create transaction for customer with total amount to pay.
     */
    private function buildCustomerTransaction(): void
    {
        $customerId = $this->ancillaryCost->invoice->customer_id;
        $subject_id = Customer::find($customerId)->subject->id;

        $this->transactions[] = [
            'subject_id' => $subject_id,
            'desc' => __('Customer total'),
            'value' => $this->ancillaryCost->amount,
        ];
    }
}
