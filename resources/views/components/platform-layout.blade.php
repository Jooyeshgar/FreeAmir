@props(['title' => config('app.name')])

<x-app-layout :title="$title">
    {{ $slot }}
</x-app-layout>
