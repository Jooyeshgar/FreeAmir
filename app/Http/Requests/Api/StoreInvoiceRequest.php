<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\StoreInvoiceRequest as SharedStoreInvoiceRequest;
use App\Models\Customer;

class StoreInvoiceRequest extends SharedStoreInvoiceRequest
{
    /**
     * Adapt the API payload onto the shared web form contract:
     * items[] become transactions[] with composite item ids and Gregorian
     * Y-m-d dates are converted to Jalali for the shared normalizer.
     */
    protected function prepareForValidation(): void
    {
        $date = trim((string) $this->input('date'));

        if (preg_match('/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})$/', $date, $matches) && (int) $matches[1] >= 1700) {
            $this->merge(['date' => convertToJalali(str_replace('/', '-', $date), true)]);
        }

        if (is_array($this->input('items'))) {
            $this->merge([
                'transactions' => collect($this->input('items'))->map(fn (array $item) => [
                    'item_id' => $item['item_type'].'-'.$item['item_id'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit_price'],
                    'discount' => $item['unit_discount'] ?? 0,
                    'vat' => $item['vat'],
                    'desc' => $item['description'] ?? null,
                    'total' => (float) $item['quantity'] * (float) $item['unit_price'],
                ])->all(),
            ]);
        }

        if (! $this->boolean('approve')) {
            $this->request->remove('approve');
        }

        parent::prepareForValidation();
    }

    public function withValidator($validator)
    {
        parent::withValidator($validator);

        $validator->after(function ($validator) {
            $customerId = $this->input('customer_id');

            if ($customerId && ! Customer::query()->whereKey($customerId)->exists()) {
                $validator->errors()->add('customer_id', __('The selected customer is invalid.'));
            }
        });

        return $validator;
    }
}
