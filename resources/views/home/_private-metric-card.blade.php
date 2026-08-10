@php($metricTheme = $privateMetricThemes[$metric['theme']])

<article
    data-private-metric="{{ $metric['metric'] }}"
    x-data="privateHomeMetric(@js(route('home.summary', ['metric' => $metric['metric']])))"
    @home-private-reset.window="reset()"
    class="group rounded-2xl border p-4 transition duration-300 hover:-translate-y-0.5 hover:shadow-md {{ $metricTheme['card'] }}">
    <div class="flex items-start justify-between gap-3">
        <div>
            <h3 class="text-sm font-semibold text-base-content">{{ $metric['title'] }}</h3>
            <p class="mt-1 text-xs text-base-content/50">{{ $metric['description'] }}</p>
        </div>
        <span class="rounded-lg p-2 {{ $metricTheme['icon'] }}">
            <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $metric['iconPath'] ?? 'M4 19V5h16v14H4Zm4-4 3-3 2 2 3-4' }}" />
            </svg>
        </span>
    </div>

    <div class="mt-5 min-h-8" aria-live="polite">
        <span x-show="!revealed" class="font-mono text-xl tracking-[0.3em] text-base-content/35">••••••</span>
        <span x-show="revealed && loading" style="display: none" class="loading loading-dots loading-sm {{ $metricTheme['loading'] }}"></span>
        <p x-show="revealed && loaded" style="display: none" class="text-xl font-bold text-base-content">
            <span x-text="formattedValue"></span>
            <span class="text-xs font-normal text-base-content/55" x-text="unit"></span>
        </p>
        <div x-show="revealed && error" style="display: none" class="text-xs text-error">
            <span>{{ __('Could not load this value.') }}</span>
            <button type="button" class="link ms-1" @click="retry()">{{ __('Try again') }}</button>
        </div>
    </div>

    <button type="button" class="btn btn-outline btn-xs mt-3 min-w-20 {{ $metricTheme['button'] }}" @click="toggle()" :aria-expanded="revealed.toString()">
        <span x-text="revealed ? @js(__('Hide')) : @js(__('Reveal'))"></span>
    </button>
</article>
