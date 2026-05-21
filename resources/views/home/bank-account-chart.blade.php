<article class="card border border-base-300 bg-base-100/90 shadow-sm">
    <div class="card-body p-4">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
                <h2 class="card-title text-base">{{ __('Bank Account Balance') }}</h2>
                <p class="text-xs text-base-content/55">{{ __('Per-account balance over time') }}</p>
            </div>

            <div class="flex gap-2" x-data="bankSelectHandler()" x-init="initializeBank()">
                <select class="select select-xs select-bordered" x-model="selectedBankAccount" @change="handleBankChange">
                    @foreach ($bankAccounts as $bankAccount)
                        <option value="{{ $bankAccount->id }}" {{ $loop->first ? 'selected' : '' }}>{{ $bankAccount->name }}</option>
                    @endforeach
                </select>

                <select x-model="selectedDuration" @change="handleBankChange" class="select select-xs select-bordered">
                    <option value="1">{{ '۳ ' . __('Month') }}</option>
                    <option value="2">{{ '۶ ' . __('Month') }}</option>
                    <option value="3">{{ '۹ ' . __('Month') }}</option>
                    <option value="4">{{ '۱۲ ' . __('Month') }}</option>
                </select>
            </div>
        </div>

        <div class="mt-3">
            <x-charts.account-balance :labels="[]" :datas="[]" />
        </div>
    </div>
</article>

@pushOnce('footer')
    <script>
        function bankSelectHandler() {
            return {
                selectedBankAccount: null,
                selectedDuration: null,
                initializeBank() {
                    this.selectedBankAccount = {{ $bankAccounts?->first()?->id ?? 'null' }};
                    this.selectedDuration = 1;
                    this.handleBankChange();
                },
                handleBankChange() {
                    if (!this.selectedBankAccount) return;
                    try {
                        const route = "{{ route('home.bank-account') }}";
                        fetch(`${route}?subject_id=${this.selectedBankAccount}&duration=${this.selectedDuration}`)
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

                    accountBalanceChart.data.labels = formattedLabels;
                    accountBalanceChart.data.datasets[0].data = data.datas;
                    accountBalanceChart.update();
                }
            };
        }
    </script>
@endPushOnce
