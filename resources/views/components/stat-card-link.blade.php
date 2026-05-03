@props(['title', 'value', 'link', 'type' => 'info', 'icon' => null, 'currency' => ''])

@php
    $typeClasses = [
        'base' => [
            'text' => 'text-slate-500 dark:text-slate-300',
            'btn' => 'btn-neutral',
            'btnDark' => 'dark:border-slate-500/50 dark:text-slate-200 dark:hover:bg-slate-700/70',
            'cardDark' => 'dark:border-slate-700',
            'iconDarkBg' => 'dark:bg-slate-700/70',
        ],
        'success' => [
            'text' => 'text-green-500 dark:text-emerald-300',
            'btn' => 'btn-success',
            'btnDark' => 'dark:border-emerald-500/40 dark:text-emerald-200 dark:hover:bg-emerald-500/10 dark:hover:border-emerald-400/60',
            'cardDark' => 'dark:border-emerald-500/20',
            'iconDarkBg' => 'dark:bg-emerald-500/10',
        ],
        'error' => [
            'text' => 'text-red-500 dark:text-red-300',
            'btn' => 'btn-error',
            'btnDark' => 'dark:border-red-500/40 dark:text-red-200 dark:hover:bg-red-500/10 dark:hover:border-red-400/60',
            'cardDark' => 'dark:border-red-500/20',
            'iconDarkBg' => 'dark:bg-red-500/10',
        ],
        'info' => [
            'text' => 'text-blue-500 dark:text-sky-300',
            'btn' => 'btn-info',
            'btnDark' => 'dark:border-sky-500/40 dark:text-sky-200 dark:hover:bg-sky-500/10 dark:hover:border-sky-400/60',
            'cardDark' => 'dark:border-sky-500/20',
            'iconDarkBg' => 'dark:bg-sky-500/10',
        ],
        'warning' => [
            'text' => 'text-orange-500 dark:text-amber-300',
            'btn' => 'btn-warning',
            'btnDark' => 'dark:border-amber-500/40 dark:text-amber-200 dark:hover:bg-amber-500/10 dark:hover:border-amber-400/60',
            'cardDark' => 'dark:border-amber-500/20',
            'iconDarkBg' => 'dark:bg-amber-500/10',
        ],
    ];

    $classes = $typeClasses[$type] ?? $typeClasses['info'];

    $icons = [
        'income' =>
            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />',
        'cogs' =>
            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />',
        'inventory' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />',
        'returns' =>
            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 15v-1a4 4 0 00-4-4H8m0 0l3 3m-3-3l3-3m9 14V5a2 2 0 00-2-2H6a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z" />',
    ];

    $iconSvg = $icons[$icon] ?? null;
@endphp

<div class="card bg-gray-50 border border-gray-100 {{ $classes['cardDark'] }} dark:bg-slate-800/80 dark:shadow-none dark:ring-1 dark:ring-white/5">
    <div class="card-body p-3">
        <div class="flex items-center gap-2 mb-3">
            @if ($iconSvg)
                <div class=" p-2 rounded-lg {{ $classes['iconDarkBg'] }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 {{ $classes['text'] }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        {!! $iconSvg !!}
                    </svg>
                </div>
            @endif
            <h3 class="text-sm font-semibold text-gray-700 dark:text-slate-300">{{ $title }}</h3>
        </div>
        <a href="{{ $link }}" class="btn btn-sm btn-outline {{ $classes['btn'] }} {{ $classes['btnDark'] }} gap-2 w-full hover:brightness-110 transition-all">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
            <span class="text-lg font-bold">{{ $value }}</span>
            @if ($currency)
                <span class="text-xs">{{ $currency }}</span>
            @endif
        </a>
    </div>
</div>
