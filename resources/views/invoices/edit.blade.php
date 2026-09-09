<x-app-layout title="{{ $isBeginningInventory ? __('Edit Beginning Inventory') : __('Edit Invoice') }} #{{ formatDocumentNumber($invoice->number) }}">
    <div>
        <form action="{{ route('invoices.update', $invoice) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                <h2 class="card-title">
                    @if ($isBeginningInventory)
                        {{ __('Edit Beginning Inventory') }}
                    @elseif ($isReturnServiceBuy)
                        {{ __('Edit') . ' ' . __('Return Service Buy Invoice') }}
                    @else
                        {{ __('Edit') . ' ' . ($isServiceBuy ? __('Service Buy Invoice') : ($isReturnServiceBuy ? __('Return Service Buy Invoice') : $invoice_type->label())) }}
                    @endif
                </h2>
                <x-show-message-bags />

                @switch($invoice_type->valueName())
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

                    @case('beginning_inventory')
                        @include('invoices.forms.beginning_inventory')
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
        @if ($isBeginningInventory)
            @can('invoices.destroy')
                <form id="delete-beginning-inventory-form" action="{{ route('invoices.destroy', $invoice) }}" method="POST">
                    @csrf
                    @method('DELETE')
                </form>
            @endcan
        @endif
    </div>

    @pushOnce('scripts')
        <script type="module">
            jalaliDatepicker.startWatch({'persianDigits': true});
        </script>
    @endPushOnce

</x-app-layout>
