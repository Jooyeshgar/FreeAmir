<article class="budget-card budget-driver-card {{ $type === 'expense' ? 'is-expense' : '' }}">
    <div class="budget-card-heading">
        <div><h3 class="{{ $type === 'income' ? 'budget-positive' : 'budget-negative' }}">{{ $title }}</h3><p>{{ __('Total') }}: {{ is_null($type === 'income' ? $actualIncome : $actualExpense) ? '—' : formatNumber(abs($type === 'income' ? $actualIncome : $actualExpense)).' '.$currency }}</p></div>
        <span class="budget-driver-icon {{ $type === 'income' ? 'budget-positive' : 'budget-negative' }}" aria-hidden="true">{{ $type === 'income' ? '↑' : '↓' }}</span>
    </div>
    <div class="budget-driver-list">
        @forelse ($categoryDrivers[$type] as $driver)
            @php
                $changeIsKnown = ! is_null($driver['percent']);
                $changeIsUp = $changeIsKnown && $driver['percent'] > 0;
                $changeIsDown = $changeIsKnown && $driver['percent'] < 0;
                $changeIsFavorable = $changeIsKnown && ($type === 'income' ? $driver['percent'] > 0 : $driver['percent'] < 0);
                $changeDirection = $changeIsFavorable ? 'up' : (($changeIsKnown && $driver['percent'] != 0) ? 'down' : 'neutral');
            @endphp
            <div class="budget-driver-row">
                <div class="budget-driver-top">
                    <div class="budget-driver-subject">
                        <a href="{{ route('transactions.index', ['subject_id' => $driver['subject']->id]) }}">{{ $driver['subject']->name }}</a>
                        <small>{{ formatCode($driver['subject']->code) }}</small>
                    </div>
                    <div class="budget-driver-amount">{{ formatNumber($driver['amount']) }}</div>
                    <div class="budget-driver-trend" data-direction="{{ $changeDirection }}">
                        @if ($changeIsKnown)
                            <span aria-hidden="true">{{ $changeIsUp ? '↑' : ($changeIsDown ? '↓' : '→') }}</span> {{ localizeNumber(number_format(abs($driver['percent']), 1)) }}%
                        @elseif (! $profitability['comparisonUnavailable'] && ($driver['previous'] ?? 0) <= 0)
                            {{ __('New') }}
                        @else
                            —
                        @endif
                    </div>
                </div>
                <div class="budget-driver-track" role="img" aria-label="{{ __('Share') }}: {{ is_null($driver['share']) ? '—' : localizeNumber(number_format($driver['share'], 1)).'%' }}"><span data-budget-width="{{ max(0, min(100, $driver['share'] ?? 0)) }}"></span></div>
            </div>
        @empty
            <div class="budget-empty">{{ __('No actual second-level account activity is available.') }}</div>
        @endforelse
    </div>
</article>
