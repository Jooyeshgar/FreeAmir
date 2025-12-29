@props(['title', 'value', 'link', 'type' => 'info', 'icon' => null, 'currency' => ''])

@php
    $typeClasses = [
        'success' => [
            'text' => 'text-green-500',
            'btn' => 'btn-success',
        ],
        'error' => [
            'text' => 'text-red-500',
            'btn' => 'btn-error',
        ],
        'info' => [
            'text' => 'text-blue-500',
            'btn' => 'btn-info',
        ],
        'warning' => [
            'text' => 'text-orange-500',
            'btn' => 'btn-warning',
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

<div class="card bg-gray-50 border border-gray-100 }}">
    <div class="card-body p-3">
        <div class="flex items-center gap-2 mb-3">
            @if ($iconSvg)
                <div class=" p-2 rounded-lg">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 {{ $classes['text'] }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        {!! $iconSvg !!}
                    </svg>
                </div>
            @endif
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $title }}</h3>
        </div>
        <a href="{{ $link }}" class="btn btn-sm btn-outline {{ $classes['btn'] }} gap-2 w-full hover:brightness-110 transition-all">
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
