@props([
    'title' => config('app.name'),
    'managementOnly' => false,
])

@if (($managementOnly || session('interface_mode') === 'management') && auth()->user()->can('access-super-admin-panel'))
    <x-super-admin-layout :title="$title">
        <div class="super-admin-content">
            {{ $slot }}
        </div>
    </x-super-admin-layout>
@else
    <x-app-layout :title="$title">
        {{ $slot }}
    </x-app-layout>
@endif
