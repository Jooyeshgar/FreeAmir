<x-app-layout>
    <div class="card bg-base-100 shadow-xl">
        <div
            class="card-header bg-gradient-to-r from-emerald-50 to-teal-50 dark:from-gray-800 dark:to-gray-700 px-6 py-4 rounded-t-2xl border-b-2 border-success/20">
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white"><a
                    href="{{ route('products.index', ['group_name' => $productGroup->name]) }}">
                    {{ $productGroup->name }}
                </a>
            </h2>

            <p class="text-gray-700 mt-2"><strong>{{ __('Description') }}:</strong>
                {{ $productGroup->description ?? '-' }}
            </p>
        </div>

        <div class="card-body">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-3">
                <x-stat-card :title="__('VAT')" :value="formatNumber($productGroup->vat) . '%'" type="base" icon="vat" />
                <x-stat-card :title="__('Products Count')" :value="formatNumber($productGroup->products->count()) ?? '-'" type="base" icon="products" />
            </div>

            @can('reports.ledger')
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-3">
                    <x-stat-card-link :title="__('Income Subject')" :value="formatNumber(\App\Services\SubjectService::sumSubject($productGroup->incomeSubject))" :link="route('transactions.index', ['subject_id' => $productGroup->incomeSubject->id])" :currency="config('amir.currency') ?? __('Rial')" type="success"
                        icon="income" />
                    <x-stat-card-link :title="__('COGS Subject')" :value="formatNumber(\App\Services\SubjectService::sumSubject($productGroup->cogsSubject))" :link="route('transactions.index', ['subject_id' => $productGroup->cogsSubject->id])" :currency="config('amir.currency') ?? __('Rial')"
                        type="error" icon="cogs" />
                    <x-stat-card-link :title="__('Inventory Subject')" :value="formatNumber(\App\Services\SubjectService::sumSubject($productGroup->inventorySubject))" :link="route('transactions.index', ['subject_id' => $productGroup->inventorySubject->id])" :currency="config('amir.currency') ?? __('Rial')"
                        type="info" icon="inventory" />
                    <x-stat-card-link :title="__('Sales Returns Subject')" :value="formatNumber(
                        \App\Services\SubjectService::sumSubject($productGroup->salesReturnsSubject),
                    )" :link="route('transactions.index', ['subject_id' => $productGroup->salesReturnsSubject->id])" :currency="config('amir.currency') ?? __('Rial')"
                        type="warning" icon="returns" />
                </div>
            @endcan

            <div class="card-actions justify-between mt-8">
                <a href="{{ route('product-groups.index') }}" class="btn btn-ghost gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    {{ __('Back') }}
                </a>
                <a href="{{ route('product-groups.edit', $productGroup) }}" class="btn btn-primary gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    {{ __('Edit') }}
                </a>
            </div>
        </div>
</x-app-layout>
