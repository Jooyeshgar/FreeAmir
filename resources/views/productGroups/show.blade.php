<x-app-layout>
    <div class="card bg-base-100 shadow-xl">
        <div
            class="card-header bg-gradient-to-r from-emerald-50 to-teal-50 dark:from-gray-800 dark:to-gray-700 px-6 py-4 rounded-t-2xl border-b-2 border-success/20">
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white"><a
                    href="{{ route('products.index', ['group_name' => $productGroup->name]) }}">
                    {{ $productGroup->name }}
                </a>
            </h2>

            <div class="max-w-7xl mt-2">
                <div class="overflow-hidden sm:rounded-lg">
                    <p class="text-gray-700"><strong>{{ __('Description') }}:</strong>
                        {{ $productGroup->description ?? '-' }}
                    </p>
                </div>
            </div>
        </div>

        <div class="card-body">
            @can('reports.ledger')
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-3">
                    <x-stat-card-link :title="__('Income Subject')" :value="formatNumber(\App\Services\SubjectService::sumSubject($productGroup->incomeSubject))" :link="route('transactions.index', ['subject_id' => $productGroup->incomeSubject->id])" :currency="config('amir.currency') ?? __('Rial')" type="success"
                        icon="income" />
                    <x-stat-card-link :title="__('COGS Subject')" :value="formatNumber(\App\Services\SubjectService::sumSubject($productGroup->cogsSubject))" :link="route('transactions.index', ['subject_id' => $productGroup->cogsSubject->id])" :currency="config('amir.currency') ?? __('Rial')" type="error"
                        icon="cogs" />
                    <x-stat-card-link :title="__('Inventory Subject')" :value="formatNumber(\App\Services\SubjectService::sumSubject($productGroup->inventorySubject))" :link="route('transactions.index', ['subject_id' => $productGroup->inventorySubject->id])" :currency="config('amir.currency') ?? __('Rial')"
                        type="info" icon="inventory" />
                    <x-stat-card-link :title="__('Sales Returns Subject')" :value="formatNumber(
                        \App\Services\SubjectService::sumSubject($productGroup->salesReturnsSubject),
                    )" :link="route('transactions.index', ['subject_id' => $productGroup->salesReturnsSubject->id])" :currency="config('amir.currency') ?? __('Rial')"
                        type="warning" icon="returns" />
                </div>
            @endcan
</x-app-layout>
