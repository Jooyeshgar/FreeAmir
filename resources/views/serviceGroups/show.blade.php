<x-app-layout>
    <div class="card bg-base-100 shadow-xl">
        <div
            class="card-header bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-gray-800 dark:to-gray-700 px-6 py-4 rounded-t-2xl border-b-2 border-primary/20">
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
                <a href="{{ route('services.index', ['service_group_id' => $serviceGroup->id]) }}">
                    {{ $serviceGroup->name }}
                </a>
            </h2>

            <div class="flex flex-wrap gap-2 mt-2">
                @if ($serviceGroup->subject)
                    <span class="badge badge-lg badge-accent gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                        <a
                            href="{{ route('transactions.index', ['subject_id' => $serviceGroup->subject->id]) }}">{{ $serviceGroup->subject->formattedCode() }}</a>
                    </span>
                    </a>
                @endif
            </div>
        </div>

        <div class="card-body">
            <div class="grid grid-cols-3 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-3">
                <x-stat-card :title="__('VAT')" :value="formatNumber($serviceGroup->vat) . '%'" type="base" icon="vat" />
                <x-stat-card :title="__('Services Count')" :value="formatNumber($serviceGroup->services->count()) ?? '-'" type="base" icon="services" />
            </div>

            @can('reports.ledger')
                <div class="grid grid-cols-3 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-3">
                    <x-stat-card-link :title="__('Subject Balance')" :value="formatNumber(\App\Services\SubjectService::sumSubject($serviceGroup->subject) ?? 0)" :link="route('transactions.index', ['subject_id' => $serviceGroup->subject->id])" :currency="config('amir.currency') ?? __('Rial')" type="success"
                        icon="income" />
                    <x-stat-card-link :title="__('COGS Subject')" :value="formatNumber(\App\Services\SubjectService::sumSubject($serviceGroup->cogsSubject) ?? 0)" :link="route('transactions.index', ['subject_id' => $serviceGroup->cogsSubject->id])" :currency="config('amir.currency') ?? __('Rial')"
                        type="error" icon="cogs" />
                    <x-stat-card-link :title="__('Sales Returns Subject')" :value="formatNumber(
                        \App\Services\SubjectService::sumSubject($serviceGroup->salesReturnsSubject) ?? 0,
                    )" :link="route('transactions.index', ['subject_id' => $serviceGroup->salesReturnsSubject->id])" :currency="config('amir.currency') ?? __('Rial')"
                        type="warning" icon="returns" />
                </div>
            @endcan

            <div class="card-actions justify-between mt-8">
                <a href="{{ route('service-groups.index') }}" class="btn btn-ghost gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    {{ __('Back') }}
                </a>
                <a href="{{ route('service-groups.edit', $serviceGroup) }}" class="btn btn-primary gap-2">
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
