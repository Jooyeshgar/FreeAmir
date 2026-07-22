@props(['title' => config('app.name')])

@can('access-super-admin-panel')
    <x-super-admin-layout :title="$title">
        <div class="super-admin-content">
            {{ $slot }}
        </div>
    </x-super-admin-layout>
@else
    <x-app-layout :title="$title">
        {{ $slot }}
    </x-app-layout>
@endcan
