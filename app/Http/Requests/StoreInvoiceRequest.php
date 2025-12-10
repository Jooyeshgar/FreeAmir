<?php

namespace App\Http\Requests;

use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Adjust permission if you have a policy/ability; keep permissive for now
        return auth()->check();
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Normalize top-level scalars
        $this->merge([
            'date' => convertToGregorian($this->input('date')),
            'invoice_id' => convertToInt($this->input('invoice_id')),
            'invoice_number' => convertToInt($this->input('invoice_number')),
            'subtractions' => convertToFloat($this->input('subtraction', 0)),
            'customer_id' => convertToInt($this->input('customer_id')),
        ]);

        if (str_contains($this->input('document_number'), '/')) {
            $this->merge([
                'document_number' => convertToFloat(str_replace('/', '.', $this->input('document_number'))),
            ]);
        } else {
            $this->merge([
                'document_number' => convertToFloat($this->input('document_number')),
            ]);
        }

        // Normalize transactions numeric fields and ids
        if ($this->has('transactions') && is_array($this->input('transactions'))) {
            $transactions = collect($this->input('transactions'))
                ->map(function ($t) {
                    return [
                        'item_id' => explode('-', $t['item_id'])[1] ?? null,
                        'item_type' => explode('-', $t['item_id'])[0] ?? null,
                        'vat' => isset($t['vat']) ? convertToFloat($t['vat']) : null,
                        'desc' => $t['desc'] ?? null,
                        'quantity' => isset($t['quantity']) ? convertToFloat($t['quantity']) : 1,
                        'unit_discount' => isset($t['off']) ? convertToFloat($t['off']) : 0,
                        'unit' => isset($t['unit']) ? convertToFloat($t['unit']) : null,
                        'total' => isset($t['total']) ? convertToFloat($t['total']) : null,
                    ];
                })
                ->toArray();
            $this->merge(['transactions' => $transactions]);
        }
    }

    /**
     * Validate warehouse quantity for "Sell" invoice type and check for duplicate items.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $transactions = $this->input('transactions', []);
            $invoiceType = $this->input('invoice_type');
            $inputDate = $this->input('date');
            $invoice = $this->route('invoice');
            $isApproved = $this->input('approve');
            $invoiceNumber = $this->input('invoice_number');

            if (! in_array($invoiceType, ['sell', 'buy'])) {
                return;
            }

            $productIds = [];
            $serviceIds = [];

            foreach ($transactions as $index => $transaction) {
                $itemId = $transaction['item_id'];
                $itemType = $transaction['item_type'];

                // Products must be unique in transactions
                if ($itemType === 'product') {
                    if (in_array($itemId, $productIds)) {
                        $validator->errors()->add(
                            "transactions.{$index}.item_id",
                            __('Each product must be unique in the transaction list.')
                        );
                    } else {
                        $productIds[] = $itemId;
                    }
                }

                // Services must be unique in transactions
                if ($itemType === 'service') {
                    if (in_array($itemId, $serviceIds)) {
                        $validator->errors()->add(
                            "transactions.{$index}.item_id",
                            __('Each service must be unique in the transaction list.')
                        );
                    } else {
                        $serviceIds[] = $itemId;
                    }
                }

                // Product quantity Check in warehouse
                if ($transaction['item_type'] === 'product' && isset($transaction['item_id']) && $transaction['quantity'] && $isApproved) {
                    $product = Product::find($transaction['item_id']);

                    if (! $product) {
                        continue;
                    }

                    $availableQuantity = $product->quantity;

                    if ($invoice) {
                        $oldItem = $invoice->items()
                            ->where('itemable_type', Product::class)
                            ->where('itemable_id', $transaction['item_id'])
                            ->first();

                        if ($oldItem) {
                            if ($invoice->invoice_type === InvoiceType::SELL) {
                                $availableQuantity += $oldItem->quantity;
                            } elseif ($invoice->invoice_type === InvoiceType::BUY) {
                                $availableQuantity -= $oldItem->quantity;
                            }
                        }
                    }
                    if ($transaction['quantity'] > $availableQuantity && $invoiceType === 'sell' && ! $product->oversell) {
                        $validator->errors()->add(
                            "transactions.{$index}.quantity",
                            "{$availableQuantity} ".__('item(s) of')." '{$product->name}' ".__('are available.')
                        );
                    }

                    $morphType = $transaction['item_type'] === 'product' ? Product::class : Service::class;

                    if ($morphType !== Product::class) {
                        continue;
                    }
                }
            }
        });

        return $validator;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $invoice = $this->route('invoice');
        $isEditing = $invoice !== null;

        return [
            'title' => 'nullable|string|min:2|max:255',
            'description' => 'nullable|string',
            'date' => 'required|date',

            'invoice_type' => ['required', Rule::in(array_column(InvoiceType::cases(), 'value'))],
            'customer_id' => 'required|exists:customers,id|integer',
            'invoice_id' => Rule::when($invoice !== null, ['required', 'integer', 'exists:invoices,id']),
            'document_number' => [
                'required',
                'decimal:0,2',
                Rule::unique('documents', 'number')
                    ->where(function ($query) {
                        return $query->where('company_id', session('active-company-id'));
                    })
                    ->ignore($isEditing ? $invoice->document_id : null),
            ],
            'invoice_number' => [
                'required',
                'integer',
                Rule::unique('invoices', 'number')
                    ->where(function ($query) {
                        return $query->where('company_id', session('active-company-id'))->where('invoice_type', $this->input('invoice_type'));
                    })
                    ->ignore($isEditing ? $invoice->id : null),
            ],

            'subtractions' => 'nullable|numeric|min:0',

            'transactions' => 'required|array|min:1',

            'transactions.*.item_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) {
                    preg_match('/transactions\.(\d+)\.item_id/', $attribute, $matches);
                    $index = $matches[1] ?? null;
                    $type = $this->input("transactions.$index.item_type");

                    if ($type === 'product' && ! Product::where('id', $value)->exists()) {
                        $fail(__('The selected product is invalid.'));
                    } elseif ($type === 'service' && ! Service::where('id', $value)->exists()) {
                        $fail(__('The selected service is invalid.'));
                    }
                },
            ],

            'transactions.*.item_type' => 'required|string|in:product,service',
            'transactions.*.vat' => 'required|numeric|min:0|max:100',
            'transactions.*.desc' => 'nullable|string|max:500',
            'transactions.*.quantity' => 'required|numeric|min:1',
            'transactions.*.unit_discount' => 'required|numeric|min:0',
            'transactions.*.unit' => 'required|numeric|min:0',
            'transactions.*.total' => 'required|numeric|min:0',
        ];
    }

    /**
     * Custom validation messages for invoice rules.
     */
    public function messages(): array
    {
        return [
            'title.required' => __('The Title field is required.'),
            'title.string' => __('The Title field must be a valid string.'),
            'title.min' => __('The Title must be at least :min characters.'),
            'title.max' => __('The Title must not be greater than :max characters.'),

            'description.string' => __('The Description field must be a valid string.'),

            'date.required' => __('The Date field is required.'),
            'date.date' => __('The Date field must be a valid date.'),

            'invoice_type.required' => __('Please select the invoice type.'),
            'invoice_type.in' => __('The selected invoice type is invalid.'),

            'customer_id.required' => __('Please select the customer.'),
            'customer_id.exists' => __('The selected customer is invalid.'),
            'customer_id.integer' => __('The customer field must be an integer.'),

            'invoice_id.integer' => __('The invoice ID field must be an integer.'),
            'invoice_id.exists' => __('The selected invoice ID is invalid.'),

            'document_number.required' => __('The document number field is required.'),
            'document_number.decimal' => __('The document number field must be a decimal number.'),
            'document_number.unique' => __('This document number has already been used for this company.'),

            'invoice_number.required' => __('The invoice number field is required.'),
            'invoice_number.integer' => __('The invoice number field must be an integer.'),
            'invoice_number.unique' => __('This invoice number has already been used for this company.'),

            'subtractions.numeric' => __('The subtractions must be a number.'),
            'subtractions.min' => __('The subtractions may not be negative.'),

            'transactions.required' => __('At least one transaction row is required.'),
            'transactions.array' => __('The transaction field must be a valid array.'),
            'transactions.min' => __('At least one transaction row must be provided.'),

            'transactions.*.item_id.required' => __('The item id is required for each row.'),
            'transactions.*.item_id.integer' => __('The item id must be an integer.'),
            'transactions.*.item_id.exists' => __('The selected item id does not exist.'),
            'transactions.*.item_id.distinct' => __('The item id must be unique for each row.'),

            'transactions.*.desc.string' => __('The Row description must be a valid string.'),
            'transactions.*.desc.max' => __('The Row description may not be greater than :max characters.'),

            'transactions.*.quantity.required' => __('The Quantity is required for each row.'),
            'transactions.*.quantity.numeric' => __('The Quantity must be a number.'),
            'transactions.*.quantity.min' => __('The Quantity must be at least :min.'),
        ];
    }
}
