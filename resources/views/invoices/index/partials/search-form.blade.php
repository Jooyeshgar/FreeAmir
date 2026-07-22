<form action="{{ route('invoices.index') }}" method="GET" class="w-full mb-2">
    <div class="hidden">
        <x-input name="invoice_type" value="{{ $invoiceType }}" />
        @if ($showServiceBuy)
            <x-input name="service_buy" value="{{ request('service_buy') }}" />
        @endif
    </div>
    <div class="flex gap-2">
        <div class="[&_.input]:input-sm w-30">
            <x-input name="number" value="{{ request('number') }}" placeholder="{{ __('Invoice Number') }}" />
        </div>
        <div class="[&_.input]:input-sm w-70">
            <x-input name="text" value="{{ request('text') }}" placeholder="{{ __('Search by customer name or transaction description') }}" />
        </div>
        <div class="[&_.input]:input-sm w-30">
            <x-date-picker name="start_date" class="w-full" placeholder="{{ __('Start date') }}" value="{{ request('start_date') }}"></x-date-picker>
        </div>
        <div class="[&_.input]:input-sm w-30">
            <x-date-picker name="end_date" class="w-full" placeholder="{{ __('End date') }}" value="{{ request('end_date') }}"></x-date-picker>
        </div>
        <div>
            <select name="status" id="status" class="select select-sm w-30">
                <option value="all">{{ __('All Invoices') }}</option>
                @foreach (\App\Enums\InvoiceStatus::cases() as $status)
                    @if ($isSellWorkflow ? $status->isPending() : ($status->isReadyToApprove() || $status->isPreInvoice() || $status->isRejected()))
                        @continue
                    @endif
                    <option value="{{ $status->valueName() }}" @selected($status->valueName() == request('status'))>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </select>
        </div>
        @if ($showMoadian)
            <div>
                <select name="moadian_status" id="moadian_status" class="select select-sm w-30">
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
            <x-checkbox name="voided" id="voided" :title="__('Voided')" value="1" :checked="request('voided') == '1'" />
        @endif
        <div>
            <button type="submit" class="btn btn-sm btn-neutral">{{ __('Search') }}</button>
        </div>
        <div>
            <a href="{{ route('invoices.export', request()->except('page')) }}" class="btn btn-sm btn-outline">
                {{ __('Export CSV') }}
            </a>
        </div>
    </div>
</form>
