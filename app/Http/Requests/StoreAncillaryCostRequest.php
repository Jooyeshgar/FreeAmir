<?php

namespace App\Http\Requests;

use App\Enums\AncillaryCostType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        $vatPrice = 0;
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
            $vatPrice = $total * ($this->input('vat') ?? 0) / 100;
            $total += $total * ($this->input('vat') ?? 0) / 100;
        }

        $this->merge([
            'vatPrice' => convertToFloat($vatPrice),
            'amount' => convertToFloat($total),
            'type' => $this->input('type'),
            'vatPercentage' => convertToFloat(($this->input('vat') ?? 0)),
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
            'vatPrice' => 'nullable|numeric|min:0',
            'vatPercentage' => 'nullable|numeric|min:0|max:100',
            'date' => 'required|date',
            'type' => ['required', Rule::in(array_column(AncillaryCostType::cases(), 'value'))],
            'ancillaryCosts' => 'required|array',
            'ancillaryCosts.*.product_id' => 'required|integer|exists:products,id',
            'ancillaryCosts.*.amount' => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => __('The Type field is required.'),
            'type.in' => __('The selected Type is invalid.'),
            'vatPercentage.numeric' => __('VAT must be a number.'),
            'vatPercentage.min' => __('VAT must be at least :min.'),
            'vatPercentage.max' => __('VAT may not be greater than :max.'),
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
