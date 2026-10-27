@php
    $change = $comparison['change'] ?? null;
    $direction = is_null($change) || $change == 0 || ($neutral ?? false) ? 'neutral' : ((($change > 0) === ($increaseIsGood ?? true)) ? 'up' : 'down');
    $formattedChange = is_null($change)
        ? null
        : (($compact ?? false) ? formatCompactCurrency($change, $currency ?? null) : formatNumber($change));
@endphp
<span class="budget-change" data-direction="{{ $direction }}">
    @if (is_null($change))
        <span>{{ __('Comparison unavailable') }}</span>
    @else
        <span class="font-semibold">{{ $change > 0 ? '↑' : ($change < 0 ? '↓' : '→') }} {{ $formattedChange }}{{ ($points ?? false) ? ' '.__('percentage points') : '' }}</span>
        @if (! is_null($comparison['percent'] ?? null))
            <span>({{ localizeNumber(number_format(abs($comparison['percent']), 1)) }}%)</span>
        @endif
    @endif
</span>
