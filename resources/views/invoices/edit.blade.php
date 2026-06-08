<x-app-layout title="{{ __('Edit Invoice') }} #{{ formatDocumentNumber($invoice->number) }}">
    <div>
        <form action="{{ route('invoices.update', $invoice) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                <h2 class="card-title">
                    @if ($isReturnServiceBuy)
                        {{ __('Edit') . ' ' . __('Return Service Buy Invoice') }}
                    @else
                        {{ __('Edit') . ' ' . ($isServiceBuy ? __('Service Buy Invoice') : ($isReturnServiceBuy ? __('Return Service Buy Invoice') : $invoice_type->label())) }}
                    @endif
                </h2>
                <x-show-message-bags />

                @switch($invoice_type->value)
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
                        @include('invoices.forms.return_buy')
                    @break

                    @default
                        <p>{{ __('Invalid invoice type') }}</p>
                @endswitch
            </div>
        </form>
    </div>

    @pushOnce('scripts')
        <script type="module">
            jalaliDatepicker.startWatch({'persianDigits': true});
        </script>
    @endPushOnce

</x-app-layout>
