@props(['title', 'value', 'link', 'type' => 'info', 'icon' => null, 'currency' => ''])

@php
    $typeClasses = [
        'base' => [
            'text' => 'text-slate-600 dark:text-slate-300',
            'iconBg' => 'bg-slate-100/80 dark:bg-slate-700/70',
            'border' => 'border-slate-200/80 dark:border-slate-700',
        ],
        'success' => [
            'text' => 'text-emerald-600 dark:text-emerald-300',
            'iconBg' => 'bg-emerald-50 dark:bg-emerald-500/10',
            'border' => 'border-emerald-100/80 dark:border-emerald-500/20',
        ],
        'error' => [
            'text' => 'text-red-600 dark:text-red-300',
            'iconBg' => 'bg-red-50 dark:bg-red-500/10',
            'border' => 'border-red-100/80 dark:border-red-500/20',
        ],
        'info' => [
            'text' => 'text-blue-600 dark:text-sky-300',
            'iconBg' => 'bg-blue-50 dark:bg-sky-500/10',
            'border' => 'border-blue-100/80 dark:border-sky-500/20',
        ],
        'warning' => [
            'text' => 'text-amber-600 dark:text-amber-300',
            'iconBg' => 'bg-amber-50 dark:bg-amber-500/10',
            'border' => 'border-amber-100/80 dark:border-amber-500/20',
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
        'info' =>
            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />',
    ];

    $iconSvg = $icons[$icon] ?? null;
@endphp

<a href="{{ $link }}"
    class="group p-3 rounded-xl border {{ $classes['border'] }} bg-white/90 shadow-sm shadow-slate-200/60 flex items-center justify-between gap-3 transition-colors hover:bg-white hover:shadow-md hover:shadow-slate-200/70 focus:outline-none focus:ring-2 focus:ring-sky-500/30 dark:bg-slate-800/80 dark:shadow-none dark:ring-1 dark:ring-white/5 dark:hover:bg-slate-800 dark:focus:ring-sky-300/30">
    <div class="flex flex-col justify-center flex-grow min-w-0 pl-4">
        <span class="text-slate-500 dark:text-slate-300 font-medium text-sm mb-1">{{ $title }}</span>

        <div class="flex items-baseline gap-2 min-w-0">
            <span class="font-bold text-slate-800 dark:text-slate-50 text-xl p-1 leading-tight break-words">
                {{ $value }}
            </span>
            @if ($currency)
                <span class="text-slate-400 dark:text-slate-400 text-xs flex-shrink-0">{{ $currency }}</span>
            @endif
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 flex-shrink-0 {{ $classes['text'] }} opacity-70 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
        </div>
    </div>

    <div class="flex-shrink-0">
        <div class="w-12 h-12 rounded-xl flex items-center justify-center {{ $classes['text'] }} {{ $classes['iconBg'] }} ring-1 ring-inset ring-slate-900/5 dark:ring-white/10">
            @if ($iconSvg)
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 stroke-current" fill="none" viewBox="0 0 24 24">
                    {!! $iconSvg !!}
                </svg>
            @else
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 stroke-current" fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            @endif
        </div>
    </div>
</a>
