<x-app-layout :title="__('Invoices')">
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            @switch(request('invoice_type'))
                @case('sell')
                    @include('invoices.index.sell')
                    @break
                @case('buy')
                    @include('invoices.index.buy')
                    @break
                @case('return_sell')
                    @include('invoices.index.return_sell')
                    @break
                @case('return_buy')
                    @include('invoices.index.return_buy')
                    @break
                @case('void')
                    @include('invoices.index.void')
                    @break
                @default
                    <x-show-messages message="{{ __('Please select invoice type to show invoices') }}" type="info" />                    
            @endswitch
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.change-status-form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    if (confirm('{{ __('This invoice has warnings for change its status, are you sure to change status?') }}')) {
                        this.submit();
                    }
                });
            });
        });
    </script>
</x-app-layout>
