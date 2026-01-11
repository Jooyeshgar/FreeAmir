<x-app-layout>
    <div class="card bg-base-100 shadow-xl">
        <div
            class="card-header bg-gradient-to-r from-emerald-50 to-teal-50 dark:from-gray-800 dark:to-gray-700 px-6 py-4 rounded-t-2xl border-b-2 border-success/20">
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
                {{ $bankAccount->name }}
            </h2>

            <div class="flex flex-wrap gap-2 mt-2">
                @if ($bankAccount->subject)
                    <span class="badge badge-lg badge-accent gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                        <a
                            href="{{ route('transactions.index', ['subject_id' => $bankAccount->subject->id]) }}">{{ $bankAccount->subject->formattedCode() }}</a>
                    </span>
                @endif
            </div>
        </div>

        <div class="card-body">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-3">
                <x-stat-card :title="__('Account number')" :value="convertToFarsi($bankAccount->number)" type="success" />
                <x-stat-card :title="__('Account type')" :value="convertToFarsi($bankAccount->type)" type="info" />
                <x-stat-card :title="__('Owner')" :value="$bankAccount->owner ?? '-'" type="info" />
                @can('reports.ledger')
                    <x-stat-card-link :title="__('Subject Balance')" :value="formatNumber(\App\Services\SubjectService::sumSubject($bankAccount->subject, true, false) ?? 0)"
                        :link="route('transactions.index', ['subject_id' => $bankAccount->subject->id])" :currency="config('amir.currency') ?? __('Rial')" type="success"
                        icon="income" />
                @endcan
            </div>

            <div class="divider text-lg font-semibold">{{ __('Bank Info') }}</div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-3">
                <x-stat-card :title="__('Name')" :value="$bankAccount->bank->name" type="info" />
                <x-stat-card :title="__('Phone')" :value="convertToFarsi($bankAccount->bank_phone ?? '-')" type="info" />
                <x-stat-card :title="__('Website')" :value="$bankAccount->bank_web_page ?? '-'" type="info" />
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 gap-4 mb-3">
                <x-stat-card :title="__('Branch')" :value="$bankAccount->bank_branch ?? '-'" type="info" />
                <x-stat-card :title="__('Address')" :value="$bankAccount->bank_address ?? '-'" type="info" />
            </div>
            <div class="divider text-lg font-semibold">{{ __('Description') }}</div>
                <div class="alert bg-base-200 shadow-sm mb-6">
                    <div>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-info shrink-0 w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span>{{ $bankAccount->desc }}</span>
                    </div>
                </div>
            </div>

            <div class="card-actions justify-between mt-8">
                <a href="{{ route('bank-accounts.index') }}" class="btn btn-ghost gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    {{ __('Back') }}
                </a>
                <a href="{{ route('bank-accounts.edit', $bankAccount) }}" class="btn btn-primary gap-2">
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
