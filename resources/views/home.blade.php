<x-app-layout :title="__('Dashboard')">
    <x-show-message-bags />

    <main class="mt-6 space-y-4">

        @include('home.database-actions')

        @include('home.header')

        @if ($hasBusinessPerms)
            @if ($canFinancial)
                @include('home.financial-metrics')
            @endif

            @if ($canSales || $canInventory)
                @include('home.sales-metrics')
            @endif

            @if ($canFinancial)
                <section class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                    @include('home.cash-and-banks')
                    @include('home.income')
                    @include('home.profit')
                </section>

                <section class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                    @include('home.bank-account-list')
                    @include('home.bank-account-chart')
                </section>
            @endif

            @if ($canPopularItems || $canInventory)
                <section class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                    @if ($canInventory)
                        @include('home.warehouse')
                    @endif

                    @if ($canPopularItems)
                        @include('home.popular-products')
                    @endif
                </section>
            @endif

            @if ($canSales)
                <section class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                    @include('home.sell')
                    @include('home.sold-amount')
                </section>
            @endif

            @include('home.quick-access')
        @endif

        @if ($canSeePersonalPortal)
            @include('home.personal-portal')
        @endif
    </main>
</x-app-layout>
