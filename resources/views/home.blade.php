<x-app-layout :title="__('Dashboard')">
    <x-show-message-bags />

    <main class="mt-6 space-y-4">

        @include('home.database-actions')

        @include('home.header')
        @include('home.quick-access')

        @if ($hasBusinessPerms)
            <section class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                @if ($canRecentDocuments)
                    @include('home.recent-documents')
                @endif

                @if ($canRecentInvoices)
                    @include('home.recent-invoices')
                @endif

                @if ($canRecentCustomers)
                    @include('home.recent-customers')
                @endif
            </section>
        @endif

        @if ($canSeePersonalPortal)
            @include('home.personal-portal')
        @endif
    </main>
</x-app-layout>
