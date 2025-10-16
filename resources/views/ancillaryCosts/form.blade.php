<div class="grid grid-cols-3 gap-6">
    <div class="col-span-1">
        <span class="flex flex-col flex-wrap text-gray-500 w-full"> {{ __('Invoice') }} </span>
        <select name="invoice_id" id="invoice_id"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-gray-900 px-3 py-2">
                <option value="">{{ __('Select Invoice') }}</option>
                @foreach ($invoices as $invoice)
                    <option value="{{ $invoice->id }}">
                        {{ $invoice->number }}
                    </option>
                @endforeach
        </select>
    </div>

    <div class="col-span-1">
        <x-input @input="$event.target.value = $store.utils.formatNumber($event.target.value)" name="amount" class="rounded-md border-gray-300"
            id="amount" title="{{ __('Amount') }}" :value="old('amount', $ancillaryCost->amount ?? '')" placeholder="{{ __('Please insert amount') }}" />
    </div>

    <div class="col-span-1">
        <x-text-input data-jdp title="{{ __('date') }}" input_name="date" placeholder="{{ __('date') }}"
            input_value="{{ old('date') ?? convertToJalali($invoice->date ?? now()) }}" 
            label_text_class="text-gray-500 text-nowrap" input_class="datePicker">
        </x-text-input>
    </div>

    <div class="col-span-3 row-start-2">
        <x-textarea name="description" id="description" title="{{ __('Description') }}" :value="old('description', $product->description ?? '')" placeholder="{{ __('Please insert description') }}" />
    </div>
    
</div>

@pushOnce('scripts')
    <script type="module">
        jalaliDatepicker.startWatch();
    </script>
@endPushOnce