<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $isServiceBuy ? __('Create') . ' ' . __('Service Buy Invoice') : __('Create Invoice') }}
        </h2>
    </x-slot>

    <div class="">
        <form action="{{ route('invoices.store') }}" method="POST">
            @csrf
            <div class="card-body">
                <h2 class="card-title">
                    @if ($isReturnServiceBuy)
                        {{ __('Add') . ' ' . __('Return Service Buy Invoice') }}
                    @else
                        {{ __('Add') . ' ' . ($isServiceBuy ? __('Service Buy Invoice') : __($invoice_type)) }}
                    @endif
                </h2>
                <x-show-message-bags />

                @php($invoice = $invoice ?? new \App\Models\Invoice())
                @switch($invoice_type)
                    @case('sell')
                        @include('invoices.forms.sell')
                    @break

                    @case('buy')
                        @if ($isServiceBuy)
                            @include('invoices.forms.buy_service')
                        @else
                            @include('invoices.forms.buy')
                        @endif
                    @break

                    @case('return_sell')
                        @include('invoices.forms.return_sell')
                    @break

                    @case('return_buy')
                        @if ($isReturnServiceBuy)
                            @php($isServiceBuy = true)
                        @endif
                        @include('invoices.forms.return_buy')
                    @break

                    @default
                        <p>{{ __('Invalid invoice type') }}</p>
                @endswitch
            </div>
        </form>
    </div>
</x-app-layout>
