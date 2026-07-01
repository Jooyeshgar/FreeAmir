@php
    $user = auth()->user();
    $today = jdate('l، j F Y');
@endphp

<section class="flex flex-col gap-2 xl:flex-row xl:items-end xl:justify-between">
    <div>
        <h1 class="text-2xl font-bold text-base-content">
            {{ __('Welcome') }}, {{ $user->name }}
        </h1>
        <p class="mt-1 text-sm text-base-content/60">
            {{ __('Operational Workspace') }} — {{ $today }}
        </p>
    </div>
    <div class="rounded-2xl border border-primary/20 bg-primary/5 px-4 py-3 text-sm text-base-content/70 shadow-sm">
        <span class="font-semibold text-primary">{{ __('Today Focus') }}:</span>
        {{ __('Review work in progress without exposing financial totals.') }}
    </div>
</section>
