<?php

namespace App\Http\Requests;

use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Service;
use App\Models\WarehouseProductStock;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    private const DECIMAL_18_2_MAX = '9999999999999999.99';

    private const DECIMAL_10_2_MAX = '99999999.99';

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
            'include_last_years_invoices' => $this->boolean('include_last_years_invoices'),
        ]);

        $returnedInvoiceId = $this->boolean('include_last_years_invoices') ? null : $this->input('returned_invoice_id');
        if ($returnedInvoiceId) {
            $this->merge([
                'returned_invoice_id' => convertToInt($returnedInvoiceId),
            ]);
        } elseif ($this->boolean('include_last_years_invoices')) {
            $this->merge(['returned_invoice_id' => null]);
        }

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
                        'warehouse_id' => isset($t['warehouse_id']) ? convertToInt($t['warehouse_id']) : null,
                    ];
                });

            $invoiceType = $this->input('invoice_type');
            if (in_array($invoiceType, ['return_sell', 'return_buy'])) {
                $transactions = $transactions->filter(fn ($t) => ($t['quantity'] ?? 0) > 0);
            }

            $this->merge(['transactions' => $transactions->values()->toArray()]);
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
            $isApproved = $this->has('approve');

            foreach ($transactions as $index => $transaction) {
                $quantity = (float) ($transaction['quantity'] ?? 0);
                $unitPrice = (float) ($transaction['unit'] ?? 0);
                $unitDiscount = (float) ($transaction['unit_discount'] ?? 0);
                $lineAmount = $quantity * $unitPrice;

                if ($lineAmount > (float) self::DECIMAL_18_2_MAX) {
                    $validator->errors()->add(
                        "transactions.{$index}.total",
                        __('The calculated line total may not be greater than :max.', ['max' => self::DECIMAL_18_2_MAX])
                    );
                }

                if ($unitDiscount > $lineAmount) {
                    $validator->errors()->add(
                        "transactions.{$index}.unit_discount",
                        __('The unit discount may not be greater than the calculated line amount.')
                    );
                }
            }

            if (in_array($invoiceType, ['return_sell', 'return_buy']) && ! $this->boolean('include_last_years_invoices')) {
                $returnedInvoiceId = $this->input('returned_invoice_id');
                $returnedInvoice = Invoice::find($returnedInvoiceId);

                // To return an invoice, a valid invoice must be provided
                if (! $returnedInvoice) {
                    $validator->errors()->add(
                        'returned_invoice_id',
                        __('The returned invoice ID is invalid.')
                    );

                    return;
                }

                // Input invoice type must match the returned invoice type
                if ($invoiceType === 'return_sell' && $returnedInvoice->invoice_type !== InvoiceType::SELL) {
                    $validator->errors()->add(
                        'returned_invoice_id',
                        __('The returned invoice must be a sell invoice for a return sell type.')
                    );
                } elseif ($invoiceType === 'return_buy' && $returnedInvoice->invoice_type !== InvoiceType::BUY) {
                    $validator->errors()->add(
                        'returned_invoice_id',
                        __('The returned invoice must be a buy invoice for a return buy type.')
                    );
                }

                // Can not return an invoice before its date
                if ($inputDate < $returnedInvoice->date) {
                    $validator->errors()->add(
                        'date',
                        __('The invoice date must be after or equal to the returned invoice date: :date.', ['date' => $returnedInvoice->date->format('Y-m-d')])
                    );
                }

                $lastReturnedInvoicesQuery = Invoice::where('returned_invoice_id', $returnedInvoiceId);
                if ($invoice) {
                    $lastReturnedInvoicesQuery->whereNot('id', $invoice->id);
                }
                $lastReturnedInvoices = $lastReturnedInvoicesQuery->get();

                foreach ($transactions as $index => $transaction) {
                    $originalItem = $returnedInvoice->items()
                        ->where('itemable_type', $transaction['item_type'] === 'product' ? Product::class : Service::class)
                        ->where('itemable_id', $transaction['item_id'])->first();

                    if (! $originalItem) {
                        continue;
                    }

                    $sumLastReturnedInvoiceItems = 0;
                    foreach ($lastReturnedInvoices as $lastReturnedInvoice) {
                        $returnedInvoiceItem = $lastReturnedInvoice->items()
                            ->where('itemable_type', $transaction['item_type'] === 'product' ? Product::class : Service::class)
                            ->where('itemable_id', $transaction['item_id'])->first();

                        $sumLastReturnedInvoiceItems += $returnedInvoiceItem?->quantity ?? 0;
                    }
                    if ($originalItem->quantity < $transaction['quantity'] + $sumLastReturnedInvoiceItems) {
                        $validator->errors()->add(
                            "transactions.{$index}.quantity",
                            __('The addition quantity for item')." '{$originalItem->itemable->name}' ".__('and its last returned invoice items cannot exceed the original related invoice item quantity of :quantity.', ['quantity' => $originalItem->quantity])
                        );
                    }
                }

                // Prevent full return of all items in a sales return invoice
                if ($invoiceType === 'return_sell') {
                    $makeItemKey = fn (string $type, int|string $id): string => "{$type}:{$id}";

                    $submittedItems = collect($transactions)->mapWithKeys(
                        fn ($transaction) => [
                            $makeItemKey(
                                $transaction['item_type'],
                                $transaction['item_id']
                            ) => (float) $transaction['quantity'],
                        ]
                    );

                    $originalItems = $returnedInvoice->items->mapWithKeys(
                        function ($item) use ($makeItemKey) {
                            $itemType = $item->itemable_type === Product::class ? 'product' : 'service';

                            return [
                                $makeItemKey(
                                    $itemType,
                                    $item->itemable_id
                                ) => (float) $item->quantity,
                            ];
                        }
                    );

                    $isFullReturnSell = $originalItems->count() === $submittedItems->count()
                        && $originalItems->every(
                            fn ($quantity, $itemKey) => $submittedItems->get($itemKey) === $quantity
                        );

                    if ($isFullReturnSell) {
                        $validator->errors()->add(
                            'transactions',
                            __('To return a sales invoice, reduce the quantity of at least one item or remove an item. Returning all items with their original quantities is equivalent to voiding the sales invoice, so you must void it.')
                        );
                    }
                }
            }

            if (! in_array($invoiceType, ['sell', 'buy', 'return_sell', 'return_buy'])) {
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
                if ($transaction['item_type'] === 'product'
                    && isset($transaction['item_id'], $transaction['warehouse_id'])
                    && $transaction['quantity']
                    && $isApproved
                    && in_array($invoiceType, ['sell', 'return_buy'], true)) {
                    $product = Product::find($transaction['item_id']);

                    if (! $product) {
                        continue;
                    }

                    $availableQuantity = (float) WarehouseProductStock::query()
                        ->where('product_id', $product->id)
                        ->where('warehouse_id', $transaction['warehouse_id'])
                        ->value('quantity');

                    if ($invoice && $invoice->status->isApprovedOrSettled()) {
                        $oldItem = $invoice->items()
                            ->where('itemable_type', Product::class)
                            ->where('itemable_id', $transaction['item_id'])
                            ->first();

                        if ($oldItem
                            && (int) $oldItem->warehouse_id === (int) $transaction['warehouse_id']
                            && in_array($invoice->invoice_type, [InvoiceType::SELL, InvoiceType::RETURN_BUY], true)) {
                            $availableQuantity += (float) $oldItem->quantity;
                        }
                    }

                    if ($transaction['quantity'] > $availableQuantity && ! $product->oversell) {
                        $validator->errors()->add(
                            "transactions.{$index}.quantity",
                            "{$availableQuantity} ".__('item(s) of')." '{$product->name}' ".__('are available.')
                        );
                    }

                }
            }
        });

        return $validator;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $invoice = $this->route('invoice');
        $isEditing = $invoice !== null;
        $isReturnInvoice = in_array($this->input('invoice_type'), [InvoiceType::RETURN_BUY->valueName(), InvoiceType::RETURN_SELL->valueName()], true);

        $rules = [
            'title' => 'nullable|string|min:2|max:255',
            'description' => 'nullable|string',
            'date' => 'required|date',

            'invoice_type' => ['required', Rule::in(InvoiceType::valueNames())],
            'customer_id' => 'required|exists:customers,id|integer',
            'invoice_id' => Rule::when($invoice !== null, ['required', 'integer', 'exists:invoices,id']),
            'include_last_years_invoices' => 'nullable|boolean',
            'returned_invoice_id' => 'nullable|integer|exists:invoices,id',
            'document_number' => [
                'required',
                'decimal:0,2',
                Rule::unique('documents', 'number')
                    ->where(function ($query) {
                        return $query->where('company_id', getActiveCompany());
                    })
                    ->ignore($isEditing ? $invoice->document_id : null),
            ],
            'invoice_number' => [
                'required',
                'integer',
                Rule::unique('invoices', 'number')
                    ->where(function ($query) {
                        return $query->where('company_id', getActiveCompany())->where('invoice_type', InvoiceType::fromName($this->input('invoice_type')));
                    })
                    ->ignore($isEditing ? $invoice->id : null),
            ],

            'subtractions' => 'nullable|numeric|min:0|max:'.self::DECIMAL_10_2_MAX,

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
            'transactions.*.vat' => $isReturnInvoice || $isEditing
                ? 'required|numeric|min:0|max:'.self::DECIMAL_18_2_MAX
                : 'required|numeric|min:0|max:100',
            'transactions.*.desc' => 'nullable|string|max:500',
            'transactions.*.quantity' => ($isReturnInvoice ? 'required|numeric|min:0|max:' : 'required|numeric|min:1|max:').self::DECIMAL_18_2_MAX,
            'transactions.*.unit_discount' => 'required|numeric|min:0|max:'.self::DECIMAL_18_2_MAX,
            'transactions.*.unit' => 'required|numeric|min:0|max:'.self::DECIMAL_18_2_MAX,
            'transactions.*.total' => 'required|numeric|min:0|max:'.self::DECIMAL_18_2_MAX,
            'transactions.*.warehouse_id' => [
                'required_if:transactions.*.item_type,product',
                'nullable',
                'integer',
                Rule::exists('warehouses', 'id')->where('company_id', getActiveCompany()),
            ],
        ];

        return $rules;
    }
}
