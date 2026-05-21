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
            {{ __('Dashboard') }} — {{ $today }}
        </p>
    </div>
</section>
