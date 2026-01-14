<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Welcome') }}
        </h2>
    </x-slot>
    <x-show-message-bags />

    <main class="mt-10">
        <div>
            <h1 class="text-[#495057] text-[24px]">
                {{ __('Dashboard') }}
            </h1>
        </div>

        <section class="flex gap-4 max-[850px]:flex-wrap">
            @include('home.cash-and-banks')

            @can('documents.show')
                @include('home.income')
                @include('home.profit')
            @else
                @include('home.sell')
                @include('home.user-details')
                @include('home.quick-access')
            @endcan
        </section>

        @can('documents.show')
            <section class="relative z-[3] flex max-[1200px]:flex-wrap gap-4 mt-4 mb-16">
                @include('home.bank-account-list')
                @include('home.bank-account-chart')
            </section>
        @else
            @canany(['products.index', 'services.index'])
                <section class="relative z-[3] flex max-[1200px]:flex-wrap gap-4 mt-4 mb-2">
                    @include('home.popular-products')
                    @include('home.warehouse')
                </section>
            @endcanany
        @endcan
    </main>

</x-app-layout>
