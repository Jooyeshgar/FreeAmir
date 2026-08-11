<x-app-layout :title="__('Company overview')">
    <x-show-message-bags />

    <main class="mt-6 space-y-4">

        @include('reports.company-overview.database-actions')

        @if ($hasBusinessPerms)

            @if ($canFinancial)
                @include('reports.company-overview.financial-metrics')
            @endif

            @if ($canSales || $canInventory)
                @include('reports.company-overview.sales-metrics')
            @endif

            @if ($canFinancial)
                <section class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                    @include('reports.company-overview.cash-and-banks')
                    @include('reports.company-overview.income')
                    @include('reports.company-overview.profit')
                </section>

                <section class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                    @include('reports.company-overview.bank-account-list')
                    @include('reports.company-overview.bank-account-chart')
                </section>
            @endif

            @if ($canPopularItems || $canInventory)
                <section class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                    @if ($canInventory)
                        @include('reports.company-overview.warehouse')
                    @endif

                    @if ($canPopularItems)
                        @include('reports.company-overview.popular-products')
                    @endif
                </section>
            @endif

            @if ($canSales)
                <section class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                    @include('reports.company-overview.sell')
                    @include('reports.company-overview.sold-amount')
                </section>
            @endif
        @endif

        @if ($canSeePersonalPortal)
            @include('reports.company-overview.personal-portal')
        @endif
    </main>
</x-app-layout>
