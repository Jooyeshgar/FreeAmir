    <div class="w-1/2 max-[1200px]:w-full bg-white rounded-[16px]">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="text-[#495057] ms-3">
                    {{ __('Bank Account Balance') }}
                </h2>
            </div>

            <div class="flex rounded-[16px] m-1 overflow-hidden">
                <a href="{{ route('documents.index') }}" class="flex items-center justify-center bg-[#DEE2E6] text-[#242424] rounded-[16px] w-[72px] h-[56px]">
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

            <div class="text-[13px]">
                @foreach ($topTenBankAccountBalances as $bankAccountId => $balance)
                    <div class="flex justify-between mx-4 mb-4">
                        <p>
                            <a href="{{ route('transactions.index', ['subject_id' => $bankAccountId]) }}">
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
