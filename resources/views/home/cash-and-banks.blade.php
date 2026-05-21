<article class="card border border-base-300 bg-base-100/90 shadow-sm">
    <div class="card-body p-4">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
                <h2 class="card-title text-base">{{ __('Cash and banks balances') }}</h2>
                <p class="text-xs text-base-content/55">{{ __('Trend across the selected period') }}</p>
            </div>

            <div class="flex gap-2" x-data="cashTypesSelectHandler()" x-init="initializeCashBook()">
                <select x-model="selectedCashType" x-on:change="handleCashBookChange" class="select select-xs select-bordered">
                    @foreach ($cashTypes as $cashTypeName)
                        <option value="{{ $cashTypeName }}" {{ $loop->first ? 'selected' : '' }}>{{ __($cashTypeName) }}</option>
                    @endforeach
                </select>

                <select x-model="selectedDuration" x-on:change="handleCashBookChange" class="select select-xs select-bordered">
                    <option value="1">{{ '۳ ' . __('Month') }}</option>
                    <option value="2">{{ '۶ ' . __('Month') }}</option>
                    <option value="3">{{ '۹ ' . __('Month') }}</option>
                    <option value="4">{{ '۱۲ ' . __('Month') }}</option>
                </select>
            </div>
        </div>

        <div class="mt-3">
            <x-charts.cash-balance-chart :labels="[]" :datas="[]" />
        </div>
    </div>
</article>

@pushOnce('footer')
    <script>
        function cashTypesSelectHandler() {
            return {
                selectedCashType: null,
                selectedDuration: null,
                initializeCashBook() {
                    this.selectedCashType = '{{ $cashTypes[0] }}';
                    this.selectedDuration = 1;
                    this.handleCashBookChange();
                },
                handleCashBookChange() {
                    try {
                        const route = "{{ route('home.cash-banks') }}";
                        fetch(`${route}?type=${this.selectedCashType}&duration=${this.selectedDuration}`)
                            .then(res => res.json())
                            .then(data => {
                                this.updateData({
                                    labels: data.labels,
                                    datas: data.datas,
                                    sum: data.sum,
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

                    cashBalanceChart.data.labels = formattedLabels;
                    cashBalanceChart.data.datasets[0].data = data.datas;
                    cashBalanceChart.update();
                }
            };
        }
    </script>
@endPushOnce
