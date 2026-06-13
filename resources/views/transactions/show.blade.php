<x-app-layout :title="__('Transaction Details') . ' #' . localizeNumber($transaction->id)">
    <div class="mx-auto max-w-5xl space-y-6">

        <div class="card overflow-hidden bg-base-100">
            <div class="border-b border-base-300 bg-gradient-to-l from-primary/10 to-base-100 p-6">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-primary/15 text-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m-6 4h6m-6 4h4M5 4.5l1.5 1L8 4.5l1.5 1L11 4.5l1.5 1L14 4.5l1.5 1L17 4.5l1.5 1V19.5L17 18.5 15.5 19.5 14 18.5 12.5 19.5 11 18.5 9.5 19.5 8 18.5 6.5 19.5 5 18.5V4.5Z" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h1 class="text-2xl font-bold text-base-content">{{ __('Transaction Details') }}</h1>
                                <span class="badge badge-primary badge-lg font-mono">#{{ localizeNumber($transaction->id) }}</span>
                            </div>
                            <p class="mt-1 text-base-content/60">{{ $transaction->desc ?: __('No description') }}</p>
                        </div>
                    </div>
                    <div class="flex flex-col gap-2">
                        @php
                            $documentable = $transaction->document->documentable;
                            $documentableRoute = match (true) {
                                $documentable instanceof \App\Models\Invoice => [
                                    'name' => 'invoices.show',
                                    'params' => $documentable,
                                ],
                                $documentable instanceof \App\Models\AncillaryCost => [
                                    'name' => 'invoices.ancillary-costs.show',
                                    'params' => [$documentable->invoice_id ?? $documentable->invoice?->id, $documentable],
                                ],
                                default => null,
                            };
                        @endphp
                        <a href="{{ route('transactions.index') }}" class="btn btn-sm gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                            </svg>
                            {{ __('Back to Transactions') }}
                        </a>
                        @if ($documentable && $documentableRoute)
                            <a href="{{ route($documentableRoute['name'], $documentableRoute['params']) }}" class="btn btn-outline btn-info btn-sm gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                </svg>
                                {{ __('View :name', ['name' => __(class_basename($transaction->document->documentable_type))]) }} {{ localizeNumber($documentable->number) }}
                            </a>
                        @endif
                    </div>
                </div>
            </div>

            <div class="stats stats-vertical rounded-none sm:stats-horizontal">
                <div class="stat">
                    <div class="stat-figure text-error">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 17l5-5m0 0l-5-5m5 5H6" />
                        </svg>
                    </div>
                    <div class="stat-title">{{ __('Debit') }}</div>
                    <div class="stat-value text-lg text-error">{{ $transaction->debit ?: '-' }}</div>
                </div>

                <div class="stat">
                    <div class="stat-figure text-success">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 7l-5 5m0 0l5 5m-5-5h12" />
                        </svg>
                    </div>
                    <div class="stat-title">{{ __('Credit') }}</div>
                    <div class="stat-value text-lg text-success">{{ $transaction->credit ?: '-' }}</div>
                </div>

                <div class="stat">
                    <div class="stat-figure text-base-content/40">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div class="stat-title">{{ __('Document Date') }}</div>
                    <div class="stat-value text-lg text-base-content">{{ formatDate($transaction->document->date) }}</div>
                </div>
            </div>
        </div>

        <div class="grid gap-6 md:grid-cols-3">
            <div class="card h-full bg-base-100">
                <div class="card-body gap-0 p-5">
                    <div class="mb-3 flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-info/15 text-info">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5a2 2 0 011.414.586l7 7a2 2 0 010 2.828l-5 5a2 2 0 01-2.828 0l-7-7A2 2 0 014 8.172V5a2 2 0 012-2z" />
                            </svg>
                        </span>
                        <h3 class="font-semibold text-base-content">{{ __('Subject Information') }}</h3>
                    </div>

                    <div class="divide-y divide-base-200">
                        <div class="flex items-center justify-between gap-3 py-2.5">
                            <span class="text-sm text-base-content/60">{{ __('Subject Code') }}</span>
                            <span class="font-mono font-medium text-base-content">{{ $transaction->subject?->formattedCode() ?: '-' }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3 py-2.5">
                            <span class="text-sm text-base-content/60">{{ __('Subject Name') }}</span>
                            <span class="text-end font-medium text-base-content">{{ $transaction->subject?->name ?: '-' }}</span>
                        </div>
                        @if ($transaction->subject?->parent)
                            <div class="flex items-center justify-between gap-3 py-2.5">
                                <span class="text-sm text-base-content/60">{{ __('Parent Subject') }}</span>
                                <span class="text-end font-medium text-base-content">{{ $transaction->subject->parent->name }}</span>
                            </div>
                        @endif
                    </div>

                    @if ($transaction->subject_id)
                        <a href="{{ route('transactions.index', ['subject_id' => $transaction->subject_id]) }}" class="btn btn-outline btn-info btn-sm mt-auto">
                            {{ __('View Subject Transactions') }}
                        </a>
                    @endif
                </div>
            </div>

            <div class="card h-full bg-base-100">
                <div class="card-body gap-0 p-5">
                    <div class="mb-3 flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-success/15 text-success">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </span>
                        <h3 class="font-semibold text-base-content">{{ __('Document Information') }}</h3>
                    </div>

                    <div class="divide-y divide-base-200">
                        <div class="flex items-center justify-between gap-3 py-2.5">
                            <span class="text-sm text-base-content/60">{{ __('Document Number') }}</span>
                            <a href="{{ route('documents.show', $transaction->document->id) }}" class="font-semibold text-info hover:underline">
                                {{ formatDocumentNumber($transaction->document->number) }}
                            </a>
                        </div>
                        <div class="flex items-center justify-between gap-3 py-2.5">
                            <span class="text-sm text-base-content/60">{{ __('Title') }}</span>
                            <span class="text-end font-medium text-base-content">{{ $transaction->document->title ?: __('No title') }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3 py-2.5">
                            <span class="text-sm text-base-content/60">{{ __('Date') }}</span>
                            <span class="font-medium text-base-content">{{ formatDate($transaction->document->date) }}</span>
                        </div>
                    </div>

                    <a href="{{ route('documents.show', $transaction->document->id) }}" class="btn btn-outline btn-success btn-sm mt-auto">
                        {{ __('View Document') }}
                    </a>
                </div>
            </div>

            <div class="card h-full bg-base-100">
                <div class="card-body gap-0 p-5">
                    <div class="mb-3 flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-secondary/15 text-secondary">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </span>
                        <h3 class="font-semibold text-base-content">{{ __('User Information') }}</h3>
                    </div>

                    <div class="divide-y divide-base-200">
                        <div class="flex items-center justify-between gap-3 py-2.5">
                            <span class="text-sm text-base-content/60">{{ __('Created By') }}</span>
                            @can('users.show')
                                <a href="{{ route('users.show', $transaction->user) }}" class="text-end font-medium text-info hover:underline">{{ $transaction->user->name }}</a>
                            @else
                                <span class="text-end font-medium text-base-content">{{ $transaction->user->name }}</span>
                            @endcan
                        </div>
                        <div class="flex items-center justify-between gap-3 py-2.5">
                            <span class="text-sm text-base-content/60">{{ __('Created At') }}</span>
                            <span class="font-medium text-base-content">{{ formatDate($transaction->created_at) }}</span>
                        </div>
                        @if ($transaction->updated_at != $transaction->created_at)
                            <div class="flex items-center justify-between gap-3 py-2.5">
                                <span class="text-sm text-base-content/60">{{ __('Updated At') }}</span>
                                <span class="font-medium text-base-content">{{ formatDate($transaction->updated_at) }}</span>
                            </div>
                        @endif
                    </div>

                    @can('users.show')
                        <a href="{{ route('users.show', $transaction->user) }}" class="btn btn-outline btn-secondary btn-sm mt-auto">
                            {{ __('View User') }}
                        </a>
                    @endcan
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
