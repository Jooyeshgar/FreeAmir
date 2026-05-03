    <div class="home-card w-1/2 max-[1200px]:w-full">
        <div class="home-card-header">
            <div>
                <h2 class="home-card-title">
                    {{ __('Bank Account Balance') }}
                </h2>
            </div>

            <div class="flex m-1 overflow-hidden">
                <a href="{{ route('documents.index') }}" class="home-card-action">
                    {{ __('Documents') }}
                </a>
            </div>
        </div>

        <div class="home-card-body mt-4">
            <div class="flex justify-between mx-4 border-b border-b-slate-200 pb-3 mb-4 text-xs font-semibold text-slate-500 dark:border-b-slate-700 dark:text-slate-400">
                <p>
                    {{ __('Bank Name') }}
                </p>

                <p>
                    {{ __('Balance') }}
                </p>
            </div>

            <div class="text-[13px]">
                @foreach ($topTenBankAccountBalances as $bankAccountId => $balance)
                    <div class="flex justify-between mx-4 mb-4">
                        <p>
                            <a href="{{ route('transactions.index', ['subject_id' => $bankAccountId]) }}" class="home-link">
                                {{ $bankAccounts->find($bankAccountId)->name }}
                            </a>
                        </p>
                        <p>
                            {{ convertToFarsi(number_format(-1 * $topTenBankAccountBalances[$bankAccountId] ?? 0)) }}
                        </p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
