<x-app-layout :title="__('Cost and Income Dashboard')">
    <x-show-message-bags />

    <main class="mt-6 space-y-4">
        @include('reports.cost-income._metrics')

        @include('reports.cost-income._monthly')

        <section class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            @include('reports.cost-income._breakdown')
        </section>

        <section class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            @include('reports.cost-income._top-customers')
        </section>
    </main>
</x-app-layout>
