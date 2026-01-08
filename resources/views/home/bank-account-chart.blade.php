    <div class="w-1/2 max-[1200px]:w-full bg-white rounded-[16px]">
        <div class="flex justify-between items-center max-[850px]:flex-col max-[850px]:mt-4">
            <div>
                <h2 class="text-[#495057] ms-3">
                    {{ __('Bank Account Balance') }}
                </h2>
            </div>

            <div class="flex m-2 overflow-hidden" x-data="bankSelectHandler()" x-init="initializeBank()">
                <select class="select select-bordered ml-2" x-model="selectedBankAccount" @change="handleBankChange">
                    @foreach ($bankAccounts as $bankAccount)
                        <option {{ $loop->first ? 'selected' : '' }} value="{{ $bankAccount->id }}">
                            {{ $bankAccount->name }}</option>
                    @endforeach
                </select>

                <select x-model="selectedDuration" @change="handleBankChange" class="select select-bordered">
                    <option value="1">{{ '۳ ' . __('Month') }}</option>
                    <option value="2">{{ '۶ ' . __('Month') }}</option>
                    <option value="3">{{ '۹ ' . __('Month') }}</option>
                    <option value="4">{{ '۱۲ ' . __('Month') }}</option>
                </select>
            </div>
        </div>

        <div class="p-2">
            <x-charts.account-balance :labels="[]" :datas="[]" />
        </div>
    </div>

    @pushOnce('footer')
        <script>
            function bankSelectHandler() {
                return {
                    selectedBankAccount: null,
                    selectedDuration: null,
                    initializeBank() {
                        this.selectedBankAccount = {{ $bankAccounts?->first()?->id ?? '' }};
                        this.selectedDuration = 1;
                        this.handleBankChange();
                    },
                    handleBankChange() {
                        try {
                            const route = "{{ route('home.bank-account') }}";
                            const response = fetch(`${route}?subject_id=${this.selectedBankAccount}&duration=${this.selectedDuration}`)
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
