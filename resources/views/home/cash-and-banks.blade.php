@can('documents.show')
    <div class="w-1/3 max-[850px]:w-full bg-white rounded-[16px] relative">
        <div class="flex justify-between items-center max-[850px]:flex-col max-[850px]:mt-4">
            <div>
                <h2 class="text-[#495057] ms-3">
                    {{ __('Cash and banks balances') }}
                </h2>
            </div>

            <div class="flex m-2 justify-between overflow-hidden" x-data="cashTypesSelectHandler()" x-init="initializeCashBook()">
                <select x-model="selectedCashType" x-on:change="handleCashBookChange" class="select ml-2 select-bordered">
                    @foreach ($cashTypes as $cashTypeName)
                        <option {{ $loop->first ? 'selected' : '' }} value="{{ $cashTypeName }}">
                            {{ __($cashTypeName) }}</option>
                    @endforeach
                </select>

                <select x-model="selectedDuration" x-on:change="handleCashBookChange" class="select select-bordered">
                    <option value="1">{{ '۳ ' . __('Month') }}</option>
                    <option value="2">{{ '۶ ' . __('Month') }}</option>
                    <option value="3">{{ '۹ ' . __('Month') }}</option>
                    <option value="4">{{ '۱۲ ' . __('Month') }}</option>
                </select>
            </div>
        </div>

        <div class="p-2">
            <x-charts.cash-balance-chart :labels="[]" :datas="[]" />
        </div>
    </div>
@endcan

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
                        const response = fetch(`${route}?type=${this.selectedCashType}&duration=${this.selectedDuration}`)
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

                    cashBalanceChart.data.labels = formattedLabels;
                    cashBalanceChart.data.datasets[0].data = data.datas;
                    cashBalanceChart.update();
                }
            };
        }
    </script>
@endPushOnce
