<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAncillaryCostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    protected function prepareForValidation(): void
    {
        $ancillaryCostsInput = $this->input('ancillaryCosts', []);
        $processedCosts = [];
        $total = 0;

        if (! empty($ancillaryCostsInput)) {
            foreach ($ancillaryCostsInput as $key => $cost) {

                $amount = convertToFloat($cost['amount'] ?? 0);
                if ($amount >= 0) {
                    $processedCosts[] = [
                        'product_id' => $cost['product_id'] ?? null,
                        'amount' => $amount,
                    ];
                }
                $total += $amount;
            }
        }

        $this->merge([
            'amount' => convertToFloat($total),
            'date' => convertToGregorian($this->input('date')),
            'invoice_id' => convertToInt($this->input('invoice_id')),
            'ancillaryCosts' => $processedCosts,
        ]);
    }

    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:0',
            'invoice_id' => 'required|integer|exists:invoices,id',
            'date' => 'required|date',
            'type' => 'required|string',
            'ancillaryCosts' => 'nullable|array',
            'ancillaryCosts.*.product_id' => 'required|integer|exists:products,id',
            'ancillaryCosts.*.amount' => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'invoice_id.required' => __('The Invoice field is required.'),
            'invoice_id.integer' => __('The invoice ID field must be an integer.'),
            'invoice_id.exists' => __('The selected invoice ID is invalid.'),
            'date.required' => __('The Date field is required.'),
            'date.date' => __('The Date field must be a valid date.'),
            'ancillaryCosts.*.product_id.required' => __('Product is required for each ancillary cost.'),
            'ancillaryCosts.*.product_id.exists' => __('The selected product is invalid.'),
            'ancillaryCosts.*.amount.required' => __('Amount is required for each ancillary cost.'),
            'ancillaryCosts.*.amount.numeric' => __('Amount must be a number.'),
            'ancillaryCosts.*.amount.min' => __('Amount must be at least :min.'),
        ];
    }
}
