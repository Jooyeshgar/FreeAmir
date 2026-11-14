<x-app-layout :title="$bankAccount->name">
    <div class="bank-account-show card min-w-0 bg-base-100 shadow-xl [overflow-wrap:anywhere]">
        <div
            class="card-header bg-gradient-to-r from-emerald-50 to-teal-50 dark:from-gray-800 dark:to-gray-700 px-4 sm:px-6 py-4 rounded-t-2xl border-b-2 border-success/20">
            <h2 class="text-xl sm:text-2xl font-bold text-gray-800 dark:text-white break-words">
                {{ $bankAccount->name }}
            </h2>

            <div class="flex flex-wrap gap-2 mt-2">
                @if ($bankAccount->subject)
                    <span class="badge badge-lg badge-accent gap-2 max-w-full">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                        <a class="min-w-0 break-all"
                            href="{{ route('transactions.index', ['subject_id' => $bankAccount->subject->id]) }}">{{ $bankAccount->subject->formattedCode() }}</a>
                    </span>
                @endif
            </div>
        </div>

        <div class="card-body p-4 sm:p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-3">
                <x-stat-card :title="__('Account number')" :value="localizeNumber($bankAccount->number)" type="success" />
                <x-stat-card :title="__('Account type')" :value="$bankAccount->type?->label()" type="info" />
                <div class="min-w-0 overflow-hidden">
                    <x-stat-card :title="__('IBAN')" type="info">
                        <span dir="ltr" class="block max-w-full break-all text-left">{{ $bankAccount->iban }}</span>
                    </x-stat-card>
                </div>
                <x-stat-card :title="__('Owner')" :value="$bankAccount->owner ?? '-'" type="info" />
                @can('reports.ledger')
                    <x-stat-card-link :title="__('Subject Balance')" :value="formatNumber(\App\Services\SubjectService::sumSubject($bankAccount->subject, true, false) ?? 0)"
                        :link="route('transactions.index', ['subject_id' => $bankAccount->subject->id])" :currency="config('amir.currency') ?? __('Rial')" type="success"
                        icon="income" />
                @endcan
            </div>

            <div class="divider text-lg font-semibold">{{ __('Bank Info') }}</div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-3">
                <x-stat-card :title="__('Name')" :value="$bankAccount->bank->name" type="info" />
                <x-stat-card :title="__('Phone')" :value="localizeNumber($bankAccount->bank_phone ?? '-')" type="info" />
                <div class="min-w-0 overflow-hidden">
                    <x-stat-card :title="__('Website')" type="info">
                        @if ($bankAccount->bank_web_page)
                            <a dir="ltr" class="link link-primary block max-w-full break-all text-left"
                                href="{{ $bankAccount->bank_web_page }}" target="_blank" rel="noopener noreferrer">
                                {{ $bankAccount->bank_web_page }}
                            </a>
                        @else
                            <span dir="ltr" class="block text-left">-</span>
                        @endif
                    </x-stat-card>
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-3">
                <x-stat-card :title="__('Branch')" :value="$bankAccount->bank_branch ?? '-'" type="info" />
                <x-stat-card :title="__('Address')" :value="$bankAccount->bank_address ?? '-'" type="info" />
            </div>
            <div class="divider text-lg font-semibold">{{ __('Description') }}</div>
            <div class="alert bg-base-200 shadow-sm mb-6 items-start">
                <div class="min-w-0 flex items-start gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-info shrink-0 w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="min-w-0 break-words">{{ $bankAccount->desc }}</span>
                </div>
            </div>
            <div class="card-actions flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
                <a href="{{ route('bank-accounts.index') }}" class="btn btn-ghost gap-2 w-full sm:w-auto">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    {{ __('Back') }}
                </a>
                <x-closed-year-action-link :fiscal-year-closed="$fiscalYearClosed" href="{{ route('bank-accounts.edit', $bankAccount) }}" class="btn btn-primary gap-2 w-full sm:w-auto">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    {{ __('Edit') }}
                </x-closed-year-action-link>
            </div>
        </div>
    </div>
</x-app-layout>
