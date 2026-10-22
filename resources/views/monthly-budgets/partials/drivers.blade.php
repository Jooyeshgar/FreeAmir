<article class="budget-panel {{ $type === 'expense' ? 'budget-expense' : '' }}">
    <div class="budget-panel-heading">
        <div><h3 class="font-semibold">{{ $title }}</h3><p class="budget-caption">{{ __('Top five Moein accounts') }}</p></div>
        <div class="text-end"><span class="budget-caption">{{ __('Total') }} · {{ $currency }}</span><div class="font-bold tabular-nums">{{ is_null($type === 'income' ? $actualIncome : $actualExpense) ? '—' : formatNumber($type === 'income' ? $actualIncome : $actualExpense) }}</div></div>
    </div>
    <div class="overflow-x-auto">
        <table class="table budget-table">
            <thead><tr><th scope="col">{{ __('Subject') }}</th><th scope="col" class="text-end">{{ __('Actual') }} / {{ __('Share') }}</th><th scope="col" class="text-end">{{ __('Previous month') }} / {{ __('Change') }}</th></tr></thead>
            <tbody>
                @forelse ($categoryDrivers[$type] as $driver)
                    <tr>
                        <td class="min-w-44">
                            @can('subjects.show')
                                <a class="link link-hover font-medium" href="{{ route('subjects.show', $driver['subject']) }}">{{ $driver['subject']->name }} <span aria-hidden="true">↗</span></a>
                            @else
                                <span class="font-medium">{{ $driver['subject']->name }}</span>
                            @endcan
                            <div class="budget-caption">{{ formatCode($driver['subject']->code) }}</div>
                        </td>
                        <td class="text-end">
                            <strong>{{ formatNumber($driver['amount']) }}</strong>
                            <div class="budget-caption">{{ is_null($driver['share']) ? '—' : localizeNumber(number_format($driver['share'], 1)).'%' }}</div>
                            <div class="budget-share" aria-hidden="true"><span style="width: {{ max(0, min(100, $driver['share'] ?? 0)) }}%"></span></div>
                        </td>
                        <td class="text-end">
                            <div class="mb-1 text-xs text-base-content/65">{{ is_null($driver['previous']) ? '—' : formatNumber($driver['previous']) }}</div>
                            @include('monthly-budgets.partials.change', ['comparison' => $driver, 'increaseIsGood' => $type === 'income', 'neutral' => false, 'points' => false])
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="budget-empty">{{ __('No actual second-level account activity is available.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</article>
