<article class="card border border-base-300 bg-base-100/90 shadow-sm">
    <div class="card-body p-4">
        <div class="flex items-start justify-between gap-2">
            <div>
                <h2 class="card-title text-base">{{ __('Bank Account Balance') }}</h2>
                <p class="text-xs text-base-content/55">{{ __('Top accounts by balance') }}</p>
            </div>

            @can('documents.show')
                <a href="{{ route('documents.index') }}" class="btn btn-xs btn-ghost">
                    {{ __('Documents') }}
                </a>
            @endcan
        </div>

        <div class="mt-3 space-y-2 text-sm">
            <div class="flex items-center justify-between border-b border-base-300 pb-2 text-xs font-semibold text-base-content/55">
                <span>{{ __('Bank Name') }}</span>
                <span>{{ __('Balance') }}</span>
            </div>

            @forelse ($topTenBankAccountBalances as $bankAccountId => $balance)
                <div class="flex items-center justify-between rounded-lg p-2 hover:bg-base-200/70 transition">
                    <a href="{{ route('transactions.index', ['subject_id' => $bankAccountId]) }}"
                        class="link link-hover font-medium">
                        {{ $bankAccounts->find($bankAccountId)?->name ?? '-' }}
                    </a>
                    <span class="font-mono">
                        {{ localizeNumber(number_format(-1 * $balance)) }}
                    </span>
                </div>
            @empty
                <div class="rounded-lg border border-dashed border-base-300 bg-base-200/50 p-5 text-center text-sm text-base-content/60">
                    {{ __('No bank accounts found.') }}
                </div>
            @endforelse
        </div>
    </div>
</article>
