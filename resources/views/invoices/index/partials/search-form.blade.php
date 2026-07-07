<form action="{{ route('invoices.index') }}" method="GET" class="ml-auto">
    <div class="mt-4 mb-4 grid grid-cols-18 gap-3 items-end">
        <div class="hidden">
            <x-input name="invoice_type" value="{{ $invoiceType }}" />
            @if ($showServiceBuy)
                <x-input name="service_buy" value="{{ request('service_buy') }}" />
            @endif
        </div>
        <div class="col-span-2">
            <x-input name="number" value="{{ request('number') }}" placeholder="{{ __('Invoice Number') }}" />
        </div>
        <div class="col-span-4">
            <x-input name="text" value="{{ request('text') }}" placeholder="{{ __('Search by customer name or transaction description') }}" />
        </div>
        <div class="col-span-2">
            <x-date-picker name="start_date" class="w-full" placeholder="{{ __('Start date') }}" value="{{ request('start_date') }}"></x-date-picker>
        </div>
        <div class="col-span-2">
            <x-date-picker name="end_date" class="w-full" placeholder="{{ __('End date') }}" value="{{ request('end_date') }}"></x-date-picker>
        </div>
        <div class="col-span-2">
            <select name="status" id="status" class="select block w-full rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 px-3 py-2">
                <option value="all">{{ __('All Invoices') }}</option>
                @foreach (\App\Enums\InvoiceStatus::cases() as $status)
                    @if ($isSellWorkflow ? $status->isPending() : ($status->isReadyToApprove() || $status->isPreInvoice() || $status->isRejected()))
                        @continue
                    @endif
                    <option value="{{ $status->value }}" @selected($status->value == request('status'))>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </select>
        </div>
        @if ($showMoadian)
            <div class="col-span-2">
                <select name="moadian_status" id="moadian_status" class="select block w-full rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 px-3 py-2">
                    <option value="">{{ __('Moadian Status') }}</option>
                    @foreach (\App\Enums\MoadianStatus::cases() as $moadianStatusOption)
                        <option value="{{ $moadianStatusOption->value }}" @selected(request('moadian_status') === $moadianStatusOption->value)>
                            {{ $moadianStatusOption->label() }}</option>
                    @endforeach
                    <option value="not_sent" @selected(request('moadian_status') === 'not_sent')>{{ __('Not sent') }}</option>
                </select>
            </div>
        @endif
        @if ($showVoided)
            <label class="col-span-2 flex items-center gap-3 mb-2">
                <input type="checkbox" name="voided" value="1" class="checkbox checkbox-primary" @checked(request('voided') == '1') />
                <span class="label-text">{{ __('Voided') }}</span>
            </label>
        @endif
        <div class="col-span-2">
            <input type="submit" value="{{ __('Search') }}" class="btn btn-primary" />
        </div>
    </div>
</form>
