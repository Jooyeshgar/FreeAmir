@php
    $adminPanel = app(\App\Extensions\AdministrationContext::class)->panel();
@endphp

@if ($adminPanel && \Illuminate\Support\Facades\Route::has($adminPanel->dashboardRoute()))
    <a href="{{ route($adminPanel->dashboardRoute()) }}" {{ $attributes->class(['whitespace-nowrap']) }} data-testid="admin-panel-link">
        {{ __('Admin Panel') }}
    </a>
@endif
