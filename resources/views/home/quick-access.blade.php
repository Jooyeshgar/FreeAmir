<div class="w-1/3 max-[850px]:w-full bg-[#E9ECEF] rounded-[16px] relative">
    <div class="flex justify-between items-center h-[62px]">
        <h2 class="text-[#495057] ms-3">
            {{ __('Quick Access') }}
        </h2>
    </div>

    <div class="flex flex-wrap text-[#212529] mt-4 max-[850px]:mb-4">
        @can('products.index')
            <div class="w-1/2 text-center mb-4 transition-all hover:text-[#6f7c88] max-[850px]:text-xs">
                <a href="{{ route('products.index') }}">
                    {{ __('Products') }}
                </a>
            </div>
        @endcan

        @can('services.index')
            <div class="w-1/2 text-center mb-4 transition-all hover:text-[#6f7c88] max-[850px]:text-xs">
                <a href="{{ route('services.index') }}">
                    {{ __('Services') }}
                </a>
            </div>
        @endcan

        @can('customers.index')
            <div class="w-1/2 text-center mb-4 transition-all hover:text-[#6f7c88] max-[850px]:text-xs">
                <a href="{{ route('customers.index') }}">
                    {{ __('Customer List') }}
                </a>
            </div>
        @endcan

        @can('bank-accounts.index')
            <div class="w-1/2 text-center mb-4 transition-all hover:text-[#6f7c88] max-[850px]:text-xs">
                <a href="{{ route('bank-accounts.index') }}">
                    {{ __('Bank Accounts') }}
                </a>
            </div>
        @endcan

        @can('documents.create')
            <div class="w-1/2 text-center mb-4 transition-all hover:text-[#6f7c88] max-[850px]:text-xs">
                <a href="{{ route('documents.create') }}">
                    {{ __('Document Issuance') }}
                </a>
            </div>
        @endcan

        @can('reports.ledger')
            <div class="w-1/2 text-center mb-4 transition-all hover:text-[#6f7c88] max-[850px]:text-xs">
                <a href="{{ route('reports.ledger') }}">
                    {{ __('Ledger Report') }}
                </a>
            </div>
        @endcan

        @can('invoices.create')
            <div class="w-1/2 text-center mb-4 transition-all hover:text-[#6f7c88] max-[850px]:text-xs">
                <a href="{{ route('invoices.create', ['invoice_type' => 'buy']) }}">
                    {{ __('Buy Invoice Issuance') }}
                </a>
            </div>

            <div class="w-1/2 text-center mb-4 transition-all hover:text-[#6f7c88] max-[850px]:text-xs">
                <a href="{{ route('invoices.create', ['invoice_type' => 'sell']) }}">
                    {{ __('Sell Invoice Issuance') }}
                </a>
            </div>
        @endcan

        @can('management.configs.index')
            <div class="w-1/2 text-center mb-4 transition-all hover:text-[#6f7c88] max-[850px]:text-xs">
                <a href="{{ url('management/configs') }}">
                    {{ __('Configs') }}
                </a>
            </div>
        @endcan
    </div>
</div>
