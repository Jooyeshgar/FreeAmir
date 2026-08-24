@props([
    'title',
    'value',
    'unit' => null,
    'change' => null,
    'icon',
    'iconClass' => 'bg-[#16a394]/10 text-[#16a394]',
])

@php
    $displayValue = is_numeric($value) ? number_format($value) : $value;
@endphp

<article {{ $attributes->class(['group relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm transition hover:-translate-y-1 hover:border-[#16a394]/20 hover:shadow-[0_14px_45px_rgba(21,38,59,.08)] dark:border-slate-800 dark:bg-slate-900']) }}>
    <div class="flex items-start justify-between">
        <div class="{{ $iconClass }} grid h-11 w-11 place-items-center rounded-xl">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $icon }}" />
            </svg>
        </div>

        @if ($change !== null)
            <span @class([
                'rounded-full px-2.5 py-1 text-[11px] font-bold',
                'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400' => $change >= 0,
                'bg-rose-50 text-rose-600 dark:bg-rose-950/50 dark:text-rose-400' => $change < 0,
            ])>
                {{ $change >= 0 ? '↑' : '↓' }} {{ localizeNumber(abs($change)) }}٪
            </span>
        @endif
    </div>

    <p class="mt-5 text-xs font-medium text-slate-400">{{ $title }}</p>
    <div class="mt-1 flex items-baseline gap-2">
        <strong class="text-2xl font-extrabold text-[#15263b] dark:text-white">{{ localizeNumber($displayValue) }}</strong>
        @if (filled($unit))
            <span class="text-[10px] text-slate-400">{{ $unit }}</span>
        @endif
    </div>

    <div class="pointer-events-none absolute -bottom-5 -end-5 h-20 w-20 rounded-full bg-slate-50 transition duration-300 group-hover:scale-125 dark:bg-slate-800/60"></div>
</article>
