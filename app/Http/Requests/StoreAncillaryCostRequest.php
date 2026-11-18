<?php

namespace App\Http\Requests;

use App\Enums\AncillaryCostType;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAncillaryCostRequest extends FormRequest
{
    private const DECIMAL_18_2_MAX = '9999999999999999.99';

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
            $vatPrice = $total * (convertToFloat($this->input('vat') ?? 0)) / 100;
            $total += $total * (convertToFloat($this->input('vat') ?? 0)) / 100;
        }
        $invoice = $this->route('invoice');

        $this->merge([
            'allocation_method' => $this->input('allocation_method', 'manual'),
            'allocation_amount' => is_string($this->input('allocation_amount')) ? str_replace(',', '', toEnglish($this->input('allocation_amount'))) : $this->input('allocation_amount'),
            'vatPrice' => convertToFloat($vatPrice),
            'amount' => convertToFloat($total),
            'type' => $this->input('type'),
            'vatPercentage' => convertToFloat(($this->input('vat') ?? 0)),
            'date' => $invoice?->date?->toDateString(),
            'invoice_id' => $invoice?->id,
            'customer_id' => convertToInt($this->input('customer_id')),
            'ancillaryCosts' => $processedCosts,
        ]);
    }

    public function rules(): array
    {
        return [
            'allocation_method' => ['required', Rule::in(['manual', 'value', 'weight'])],
            'allocation_amount' => 'exclude_if:allocation_method,manual|required|numeric|min:0|max:'.self::DECIMAL_18_2_MAX,
            'amount' => 'required|numeric|min:0|max:'.self::DECIMAL_18_2_MAX,
            'invoice_id' => 'required|integer|exists:invoices,id',
            'customer_id' => 'required|integer|exists:customers,id',
            'vatPrice' => 'nullable|numeric|min:0|max:'.self::DECIMAL_18_2_MAX,
            'vatPercentage' => 'nullable|numeric|min:0|max:100',
            'date' => ['required', 'date'],
            'type' => ['required', Rule::in(AncillaryCostType::valueNames())],
            'ancillaryCosts' => 'required|array',
            'ancillaryCosts.*.product_id' => 'required|integer|exists:products,id',
            'ancillaryCosts.*.amount' => 'required|numeric|min:0|max:'.self::DECIMAL_18_2_MAX,
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty() || $this->input('allocation_method') === 'manual') {
                return;
            }

            // Use the invoice's products and their stored values, never submitted bases.
            $products = $this->route('invoice')->items()->where('itemable_type', Product::class)->with('itemable')->get()->groupBy('itemable_id');
            $costs = collect($this->input('ancillaryCosts'));
            $productIds = $costs->pluck('product_id');

            if ($productIds->duplicates()->isNotEmpty() || $productIds->count() !== $products->count() || $productIds->diff($products->keys())->isNotEmpty()) {
                $validator->errors()->add('ancillaryCosts', __('Automatic allocation must include each invoice product exactly once.'));

                return;
            }

            $bases = $products->map(function ($items): float {
                return max(0, $this->input('allocation_method') === 'weight'
                    ? $items->sum('quantity') * (float) $items->first()->itemable?->weight
                    : $items->sum(fn ($item) => $item->quantity * $item->unit_price - $item->discount));
            });
            $totalBasis = $bases->sum();
            if ($totalBasis <= 0) {
                $validator->errors()->add('allocation_amount', __('No positive allocation basis is available. Enter amounts manually or update product weights.'));

                return;
            }

            // Match the form's cumulative rounding, including its remainder cent.
            $cents = round((float) $this->input('allocation_amount') * 100);
            $cumulativeBasis = 0;
            $allocatedCents = 0;
            $expected = [];
            foreach ($bases as $productId => $basis) {
                $cumulativeBasis += $basis;
                $cumulativeCents = round($cents * $cumulativeBasis / $totalBasis);
                $expected[$productId] = ($cumulativeCents - $allocatedCents) / 100;
                $allocatedCents = $cumulativeCents;
            }

            foreach ($costs as $index => $cost) {
                if (abs($cost['amount'] - $expected[$cost['product_id']]) > 0.000001) {
                    $validator->errors()->add("ancillaryCosts.$index.amount", __('The product share does not match the selected allocation method and amount.'));
                }
            }
        });
    }
}
