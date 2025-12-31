<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Welcome') }}
        </h2>
    </x-slot>
    <x-show-message-bags />

    <main class="mt-10">
        <div>
            <h1 class="text-[#495057] text-[24px]">
                {{ __('Dashboard') }}
            </h1>
        </div>

        <section class="flex gap-4 max-[850px]:flex-wrap">
            <div class="w-1/3 max-[850px]:w-full bg-[#E9ECEF] rounded-[16px] relative">
                <div class="flex justify-between items-center max-[850px]:flex-col max-[850px]:mt-4">
                    <div>
                        <h2 class="text-[#495057] ms-3">
                            {{ __('Cash balances') }}

                        </h2>
                    </div>

                    <div class="flex bg-[#DEE2E6] rounded-[16px] m-1 overflow-hidden" x-data="cashBookSelectHandler()"
                        x-init="initializeCashBook()">
                        <select x-model="selectedCashBook" x-on:change="handleCashBookChange"
                            class="select bg-[#DEE2E6] text-[#495057] w-[140px] max-w-xs">
                            @foreach ($cashBooks as $item)
                                <option {{ $loop->first ? 'selected' : '' }} value="{{ $item->id }}">
                                    {{ $item->name }}</option>
                            @endforeach
                        </select>

                        <select x-model="selectedDuration" x-on:change="handleCashBookChange"
                            class="select bg-[#DEE2E6] text-[#495057] w-[120px] max-w-xs">
                            <option value="1">{{ '۳ ' . __('Month') }}</option>
                            <option value="2">{{ '۶ ' . __('Month') }}</option>
                            <option value="3">{{ '۹ ' . __('Month') }}</option>
                            <option value="4">{{ '۱۲ ' . __('Month') }}</option>
                        </select>
                    </div>
                </div>

                <div>
                    <div class="p-2">
                        <x-charts.cash-balance-chart :labels="[]" :datas="[]" />
                    </div>
                </div>
            </div>

            @can('documents.show')
                <div class="gaugeChartContainer w-1/3 max-[850px]:w-full relative bg-[#E9ECEF] rounded-[16px]">
                    <div class="flex justify-between items-center h-[62px]">
                        <h2 class="text-[#495057] ms-3">
                            {{ __('Income') }}
                        </h2>
                    </div>

                    <div class="p-2">
                        <x-charts.income-chart id="monthlyIncomeChart" :datas="$monthlyIncome" />

                    </div>
                </div>
            @else
                <div class="gaugeChartContainer w-1/3 max-[850px]:w-full relative bg-[#E9ECEF] rounded-[16px]">
                    <div class="flex justify-between items-center h-[62px]">
                        <h2 class="text-[#495057] ms-3">
                            {{ __('Sell') }}
                        </h2>
                    </div>

                    <div class="p-2">
                        <x-charts.sell-chart id="monthlySellAmountChart" :datas="$monthlySellAmount" />
                    </div>
                </div>
            @endcan

            <div class="w-1/3 max-[850px]:w-full bg-[#E9ECEF] rounded-[16px] relative">
                <div class="flex justify-between items-center h-[62px]">
                    <h2 class="text-[#495057] ms-3">
                        {{ __('Quick Access') }}
                    </h2>
                </div>

                <div class="flex flex-wrap text-[#212529] mt-4 max-[850px]:mb-4">
                    @can('customers.index')
                        <div class="w-1/2 text-center mb-4 transition-all hover:text-[#6f7c88] max-[850px]:text-xs">
                            <a href="{{ route('customers.index') }}">
                                {{ __('Customer List') }}
                            </a>
                        </div>
                    @endcan

                    @can('documents.create')
                        <div class="w-1/2 text-center mb-4 transition-all hover:text-[#6f7c88] max-[850px]:text-xs">
                            <a href="{{ route('documents.create') }}">
                                {{ __('Manual Document Issuance') }}
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

                    @can('reports.ledger')
                        <div class="w-1/2 text-center mb-4 transition-all hover:text-[#6f7c88] max-[850px]:text-xs">
                            <a href="{{ route('reports.ledger') }}">
                                {{ __('Statistical Reports') }}
                            </a>
                        </div>
                    @endcan

                    @can('bank-accounts.index')
                        <div class="w-1/2 text-center mb-4 transition-all hover:text-[#6f7c88] max-[850px]:text-xs">
                            <a href="{{ route('bank-accounts.index') }}">
                                {{ __('Financial Affairs') }}
                            </a>
                        </div>
                    @endcan

                    @can('invoices.create')
                        <div class="w-1/2 text-center mb-4 transition-all hover:text-[#6f7c88] max-[850px]:text-xs">
                            <a href="{{ route('invoices.create', ['invoice_type' => 'buy']) }}">
                                {{ __('Invoice Issuance') }}
                            </a>
                        </div>
                    @endcan

                    @can('products.index')
                        <div class="w-1/2 text-center mb-4 transition-all hover:text-[#6f7c88] max-[850px]:text-xs">
                            <a href="{{ route('products.index') }}">
                                {{ __('Products') }}
                            </a>
                        </div>
                    @endcan
                </div>
            </div>
        </section>

        @can('documents.show')
            <section class="relative z-[3] flex max-[1200px]:flex-wrap gap-4 mt-4 mb-16">
                <div class="w-1/2 max-[1200px]:w-full bg-[#E9ECEF] rounded-[16px]">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="text-[#495057] ms-3">
                                {{ __('Bank Account Balance') }}
                            </h2>
                        </div>

                        <div class="flex bg-[#DEE2E6] rounded-[16px] m-1 overflow-hidden">
                            <a href="{{ route('documents.index') }}"
                                class="flex items-center justify-center bg-[#DEE2E6] text-[#242424] font-bold rounded-[16px] w-[72px] h-[56px]">
                                {{ __('Documents') }}
                            </a>
                        </div>
                    </div>

                    <div class="text-[#495057] mt-4">
                        <div class="flex justify-between mx-4 border-b-2 border-b-[#CED4DA] pb-3 mb-4">
                            <p>
                                {{ __('Bank Name') }}
                            </p>

                            <p>
                                {{ __('Balance') }}
                            </p>
                        </div>

                        <div class="flex justify-between mx-4 text-[13px]">
                            <div>
                                @foreach ($banks as $bank)
                                    <p class="mb-4">
                                        {{ $bank->name }}
                                    </p>
                                @endforeach
                            </div>

                            <div>
                                @foreach ($banks as $bank)
                                    <p class="mb-4">
                                        {{ convertToFarsi(number_format(-1 * $bankBalances[$bank->id] ?? 0)) }}
                                    </p>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="w-1/2 max-[1200px]:w-full bg-[#E9ECEF] rounded-[16px]">
                    <div class="flex justify-between items-center max-[850px]:flex-col max-[850px]:mt-4">
                        <div>
                            <h2 class="text-[#495057] ms-3">
                                {{ __('Bank Account Balance') }}
                            </h2>
                        </div>

                        <div class="flex bg-[#DEE2E6] rounded-[16px] m-1 overflow-hidden" x-data="bankSelectHandler()"
                            x-init="initializeBank()">
                            <select class="select bg-[#DEE2E6] text-[#495057] w-full max-w-xs" x-model="selectedBank"
                                @change="handleBankChange">
                                @foreach ($banks as $item)
                                    <option {{ $loop->first ? 'selected' : '' }} value="{{ $item->id }}">
                                        {{ $item->name }}</option>
                                @endforeach
                            </select>

                            <select x-model="selectedDuration" @change="handleBankChange"
                                class="select bg-[#DEE2E6] text-[#495057] w-[120px] max-w-xs">
                                <option value="1">{{ '۳ ' . __('Month') }}</option>
                                <option value="2">{{ '۶ ' . __('Month') }}</option>
                                <option value="3">{{ '۹ ' . __('Month') }}</option>
                                <option value="4">{{ '۱۲ ' . __('Month') }}</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <div class="p-2">
                            <x-charts.account-balance :labels="[]" :datas="[]" />
                        </div>
                    </div>
                </div>
            </section>
        @else
            @canany(['products.index', 'services.index'])
                <section class="relative z-[3] flex max-[1200px]:flex-wrap gap-4 mt-4 mb-2">
                    <div class="w-1/2 max-[1200px]:w-full bg-[#E9ECEF] rounded-[16px]">
                        <div class="flex justify-between items-center">
                            <div>
                                <h2 class="text-[#495057] ms-3">{{ __('Most popular products and services') }}</h2>
                            </div>
                            <div class="flex rounded-[16px] m-1 overflow-hidden">
                                <a href="{{ route('products.index') }}"
                                    class="flex ml-4 items-center justify-center bg-[#DEE2E6] text-[#242424] rounded-[16px] w-[72px] h-[56px]">
                                    {{ __('Products') }}</a>
                                <a href="{{ route('services.index') }}"
                                    class="flex items-center justify-center bg-[#DEE2E6] text-[#242424] rounded-[16px] w-[72px] h-[56px]">
                                    {{ __('Services') }}</a>
                            </div>
                        </div>
                        <div class="text-[#495057] mt-4">
                            <div class="flex justify-between mx-4 border-b-2 border-b-[#CED4DA] pb-3 mb-4">
                                <p>{{ __('Product/Service name') }}</p>
                                <p>{{ __('Quantity') }}</p>
                            </div>
                            <div class="flex justify-between mx-4 text-[13px]">
                                <div>
                                    @foreach ($popularProductsAndServices as $popularProductAndService)
                                        <p class="mb-4">
                                            <a
                                                href="{{ route($popularProductAndService['type'] . '.show', $popularProductAndService['id']) }}">
                                                {{ $popularProductAndService['name'] }}</a>
                                        </p>
                                    @endforeach
                                </div>
                                <div>
                                    @foreach ($popularProductsAndServices as $popularProductAndService)
                                        <p class="mb-4">
                                            {{ convertToFarsi(number_format($popularProductAndService['quantity'])) }}
                                        </p>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="w-1/2 max-[1200px]:w-full bg-[#E9ECEF] rounded-[16px]">
                        <div class="flex justify-between items-center h-[62px]">
                            <h2 class="text-[#495057] ms-3">{{ __('Warehouse') }}</h2>
                        </div>
                        <div class="p-2">
                            <x-charts.warehouse-chart :datas="$monthlyWarehouse" />
                        </div>
                    </div>
                </section>
            @endcanany
        @endcan
    </main>

    @pushOnce('footer')
        <script>
            function cashBookSelectHandler() {
                return {
                    selectedCashBook: null,
                    selectedDuration: null,
                    initializeCashBook() {
                        this.selectedCashBook = {{ $cashBooks?->first()?->id ?? null }};
                        this.selectedDuration = 1;
                        this.handleCashBookChange();
                    },
                    handleCashBookChange() {
                        try {
                            const response = fetch(
                                    "{{ route('home.subject-detail') }}", {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf_token"]').getAttribute(
                                                'content'),
                                        },
                                        body: JSON.stringify({
                                            cash_book: this.selectedCashBook,
                                            duration: this.selectedDuration
                                        })
                                    })
                                .then(res => res.json())
                                .then(data => {
                                    const labels = data.labels;
                                    const datas = data.datas;
                                    const sum = data.sum;
                                    this.updateData({
                                        labels,
                                        datas,
                                        sum,
                                    });
                                });
                        } catch (error) {
                            console.error('Error fetching data:', error);
                        }
                    },
                    updateData(data) {
                        const formattedLabels = data.labels.map(label => {
                            if (label.match(/^\d{4}-\d{2}-\d{2}$/)) {
                                const [year, month, day] = label.split('-');

                                return convertToLocaleDigits(convertToJalali(year, month, day));
                            }

                            return label;
                        });

                        cashBalanceLineChart.data.labels = formattedLabels;
                        cashBalanceLineChart.data.datasets[0].data = data.datas;
                        cashBalanceLineChart.update();
                    }
                };
            }

            function bankSelectHandler() {
                return {
                    selectedBank: null,
                    selectedDuration: null,
                    initializeBank() {
                        this.selectedBank = {{ $banks?->first()?->id ?? null }};
                        this.selectedDuration = 1;
                        this.handleBankChange();
                    },
                    handleBankChange() {
                        try {
                            const response = fetch(
                                    "{{ route('home.subject-detail') }}", {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf_token"]').getAttribute(
                                                'content'),
                                        },
                                        body: JSON.stringify({
                                            cash_book: this.selectedBank,
                                            duration: this.selectedDuration
                                        })
                                    })
                                .then(res => res.json())
                                .then(data => {
                                    const labels = data.labels;
                                    const datas = data.datas;
                                    const sum = data.sum;
                                    this.updateData({
                                        labels,
                                        datas,
                                        sum,
                                    });
                                });
                        } catch (error) {
                            console.error('Error fetching data:', error);
                        }
                    },
                    updateData(data) {
                        const formattedLabels = data.labels.map(label => {
                            if (label.match(/^\d{4}-\d{2}-\d{2}$/)) {
                                const [year, month, day] = label.split('-');

                                return convertToLocaleDigits(convertToJalali(year, month, day));
                            }

                            return label;
                        });

                        accountBalanceChart.data.labels = formattedLabels;
                        accountBalanceChart.data.datasets[0].data = data.datas;
                        accountBalanceChart.update();
                    }
                };
            }
        </script>
    @endPushOnce

</x-app-layout>
