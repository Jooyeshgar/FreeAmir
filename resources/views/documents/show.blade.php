<x-app-layout :title="__('Document') . ' #' . formatDocumentNumber($document->number)">
    <div class="card bg-base-100 shadow-xl">
        <div
            class="card-header bg-gradient-to-r from-blue-50 to-indigo-50 dark:text-white dark:from-gray-800 dark:to-gray-700 px-6 py-4 rounded-t-2xl border-b-2 border-primary/20">
            <div>
                <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
                    {{ __('Document') }} #{{ formatDocumentNumber($document->number) }}
                    @if ($document->title)
                        - {{ $document->title }}
                    @endif
                </h2>
                <p class="mt-1 float-end">
                    {{ __('Issued on :date', ['date' => $document->date ? formatDate($document->date) : __('Unknown')]) }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2 mt-2">
                <span class="badge badge-lg badge-primary gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M7 7h.01M7 3h10a2 2 0 012 2v4a2 2 0 01-.586 1.414l-7 7a2 2 0 01-2.828 0l-4.586-4.586A2 2 0 014 12V5a2 2 0 012-2z" />
                    </svg>
                    {{ __('Accounting Document') }}
                </span>
                @if ($isLinked && $documentableRoute)
                    <span class="badge badge-lg badge-info gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m2 8H7a2 2 0 01-2-2V6a2 2 0 012-2h7l5 5v9a2 2 0 01-2 2z" />
                        </svg>
                        <a href="{{ route($documentableRoute['name'], $documentableRoute['params']) }}" class="link link-hover">
                            {{ $linkedType }}
                        </a>
                    </span>
                @endif
                <span class="badge badge-lg {{ $document->approved_at ? 'badge-outline' : 'badge-warning' }} gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {{ $document->approved_at ? __('Approved') : __('unapproved') }}
                </span>
            </div>
        </div>

        <div class="card-body space-y-8">
            <x-show-message-bags />
            <div>
                <div class="divider text-lg font-semibold">{{ __('Transactions') }}</div>
                <div class="overflow-x-auto">
                    <table class="table w-full border-collapse border border-base-300 [&_th]:border [&_th]:border-base-300 [&_td]:border [&_td]:border-base-300">
                        <thead>
                            <tr>
                                <th class="px-4 py-3">#</th>
                                <th class="px-4 py-3 text-center">{{ __('Code') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Description') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Debit') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Credit') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $row = 0; @endphp
                            @forelse ($groupedTransactions as $group)
                                @php
                                    $first = $group->first();
                                    $alignClass = $first?->value > 0 ? 'text-left' : 'text-right';
                                @endphp
                                <tr class="bg-base-200/70 font-semibold">
                                    <td class="px-4 py-2"></td>
                                    <td class="px-4 py-2 text-center">{{ formatCode($first?->subject?->ledger() ?? '') }}</td>
                                    <td class="px-4 py-2 {{ $alignClass }}">{{ $first?->subject?->getRoot()?->name ?? '—' }}</td>
                                    <td class="px-4 py-2"></td>
                                    <td class="px-4 py-2"></td>
                                </tr>
                                @foreach ($group as $transaction)
                                    <tr class="hover:bg-base-300">
                                        <td class="px-4 py-3">{{ convertToFarsi(++$row) }}</td>
                                        <td class="px-4 py-3 text-left">
                                            <a href="{{ route('transactions.index', ['subject_id' => $transaction->subject_id]) }}" class="link link-hover"
                                                title="{{ $transaction->subject?->fullname() }}">
                                                {{ $transaction->subject?->formattedCode() ?? '—' }}
                                            </a>
                                        </td>
                                        <td class="px-4 py-3 {{ $alignClass }}">
                                            {{ $transaction->subject?->name ?? '—' }}
                                            @if ($transaction->desc)
                                                <small class="block truncate text-gray-500 dark:text-slate-400">{{ $transaction->desc }}</small>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right tabular-nums">
                                            {{ $transaction->value < 0 ? formatNumber(abs($transaction->value)) : formatNumber(0) }}
                                        </td>
                                        <td class="px-4 py-3 text-right tabular-nums">
                                            {{ $transaction->value > 0 ? formatNumber($transaction->value) : formatNumber(0) }}
                                        </td>
                                    </tr>
                                @endforeach
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-6 text-center text-gray-500 dark:text-slate-400">
                                        {{ __('There are no transactions in this document yet.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="font-semibold">
                                <td colspan="2" class="px-4 py-3 text-right text-sm text-gray-600 dark:text-slate-300">
                                    {{ __('Total entries: :count', ['count' => convertToFarsi($document->transactions->count())]) }}
                                </td>
                                <td class="px-4 py-3 text-left text-sm text-gray-600 dark:text-slate-300">
                                    {{ __('Total Document:') }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm text-gray-600 dark:text-slate-300 tabular-nums">
                                    {{ formatNumber($sumDebit) }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm text-gray-600 dark:text-slate-300 tabular-nums">
                                    {{ formatNumber($sumCredit) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    @foreach ($details as $detail)
                        <div class="card bg-base-200 shadow {{ ($detail['wide'] ?? false) ? 'md:col-span-2 lg:col-span-4' : '' }}">
                            <div class="card-body p-4">
                                <h3 class="card-title text-xs uppercase tracking-wide text-gray-500 dark:text-slate-300">{{ $detail['title'] }}</h3>
                                <p class="text-lg font-semibold text-gray-800 dark:text-slate-100">{{ $detail['value'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            @if ($document->invoice)
                <div>
                    <div class="divider text-lg font-semibold">{{ __('Invoice') }}</div>
                    <div class="overflow-x-auto">
                        <table class="table w-full">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3">{{ __('Invoice Number') }}</th>
                                    <th class="px-4 py-3">{{ __('Title') }}</th>
                                    <th class="px-4 py-3">{{ __('Type') }}</th>
                                    <th class="px-4 py-3">{{ __('Status') }}</th>
                                    <th class="px-4 py-3">{{ __('Date') }}</th>
                                    <th class="px-4 py-3 text-right">{{ __('Grand total') }}</th>
                                    <th class="px-4 py-3 text-center">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="hover:bg-base-300">
                                    <td class="px-4 py-3 font-semibold">
                                        <a href="{{ route('invoices.show', $document->invoice) }}" class="link link-hover">
                                            {{ formatDocumentNumber($document->invoice->number ?? $document->invoice->id) }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-3">{{ $document->invoice->title ?? '—' }}</td>
                                    <td class="px-4 py-3">{{ $document->invoice->invoice_type?->label() ?? '—' }}</td>
                                    <td class="px-4 py-3">
                                        <span class="badge {{ $document->invoice->status?->isApproved() ? 'badge-success' : 'badge-ghost' }}">
                                            {{ $document->invoice->status?->label() ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ $document->invoice->date ? formatDate($document->invoice->date) : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums">
                                        {{ formatNumber(($document->invoice->amount ?? 0) - ($document->invoice->subtraction ?? 0)) }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <a href="{{ route('invoices.show', $document->invoice) }}" class="btn btn-sm btn-info">{{ __('Show') }}</a>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <div>
                <div class="divider text-lg font-semibold">{{ __('Document Files') }}</div>
                @if ($documentFiles->isNotEmpty())
                    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
                        @foreach ($documentFiles as $documentFile)
                            @php
                                $extension = strtolower(pathinfo($documentFile->path ?? '', PATHINFO_EXTENSION));
                                $viewUrl = route('documents.files.view', [$document, $documentFile]);
                            @endphp
                            <div class="card bg-base-100 border border-gray-200 hover:shadow-md transition-shadow dark:border-slate-700 dark:shadow-none dark:ring-1 dark:ring-white/5">
                                <figure class="px-4 pt-4">
                                    <a href="{{ $viewUrl }}" class="block w-full h-48 overflow-hidden rounded-lg bg-gray-100 dark:bg-slate-800">
                                        @if (in_array($extension, $imageExtensions))
                                            <img src="{{ $viewUrl }}" alt="{{ $documentFile->title }}" class="w-full h-full object-cover">
                                        @else
                                            <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-gray-100 to-gray-200 dark:from-slate-800 dark:to-slate-900">
                                                @if ($extension === 'pdf')
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-20 w-20 text-red-500" fill="currentColor" viewBox="0 0 24 24">
                                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z" />
                                                        <path
                                                            d="M14 2v6h6M9.5 11.5a1.5 1.5 0 1 1 0 3h-1v2h-1v-5h2zm0 2a.5.5 0 1 0 0-1h-1v1h1zm3.5-2h1.5a1 1 0 0 1 0 2H13v1h1.5a1 1 0 0 1 0 2H13v1h-1v-6zm1 2h.5a.5.5 0 1 0 0-1H14v1h.5zm3-2H18v5h-1v-2h-.5a1.5 1.5 0 0 1 0-3zm0 2h.5a.5.5 0 1 0 0-1H17v1z" />
                                                    </svg>
                                                @elseif (in_array($extension, ['doc', 'docx']))
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-20 w-20 text-blue-500" fill="currentColor" viewBox="0 0 24 24">
                                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z" />
                                                        <path d="M14 2v6h6M10 14H8v4h2c1.1 0 2-.9 2-2s-.9-2-2-2zm0 3h-1v-2h1c.55 0 1 .45 1 1s-.45 1-1 1z" />
                                                    </svg>
                                                @elseif (in_array($extension, ['xls', 'xlsx']))
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-20 w-20 text-green-500" fill="currentColor" viewBox="0 0 24 24">
                                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z" />
                                                        <path d="M14 2v6h6M9 15l2 2-2 2M15 15l-2 2 2 2" />
                                                    </svg>
                                                @else
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-20 w-20 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                                    </svg>
                                                @endif
                                            </div>
                                        @endif
                                    </a>
                                </figure>
                                <div class="card-body p-4">
                                    <h3 class="card-title text-base">{{ $documentFile->title ?? '—' }}</h3>
                                    <div class="text-sm text-gray-500 dark:text-slate-400 space-y-1">
                                        <p>{{ $documentFile->attachBy?->name ?? '—' }}, {{ formatDate($documentFile->created_at) ?? '—' }}</p>
                                    </div>
                                    <div class="card-actions justify-between mt-3">
                                        <a href="{{ route('documents.files.download', [$document, $documentFile]) }}" class="btn btn-sm btn-info gap-1"
                                            title="{{ __('Download') }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3" />
                                            </svg>
                                            {{ __('Download') }}
                                        </a>
                                        <div class="flex gap-1">
                                            <a href="{{ route('documents.files.edit', [$document, $documentFile]) }}" class="btn btn-sm btn-warning btn-square"
                                                title="{{ __('Edit') }}">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </a>
                                            <form action="{{ route('documents.files.destroy', [$document, $documentFile]) }}" method="POST" class="inline-block"
                                                onsubmit="return confirm('{{ __('Are you sure you want to delete this file?') }}')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-error btn-square" title="{{ __('Delete') }}">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="alert bg-base-200">
                        <span>{{ __('No files are attached to this document.') }}</span>
                    </div>
                @endif
                <div class="mt-4 flex justify-end">
                    <a href="{{ route('documents.files.create', $document->id) }}" class="btn btn-primary gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        {{ __('Add new file') }}
                    </a>
                </div>
            </div>

            <div class="card-actions justify-between mt-4">
                <a href="{{ route('documents.index', request()->query()) }}" class="btn btn-ghost gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    {{ __('Back') }}
                </a>

                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('documents.print', $document) }}" class="btn btn-outline gap-2" target="_blank" rel="noopener">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M7 8h10M7 12h10m-7 8h4m-7-4h10V8a2 2 0 00-2-2h-2V4a2 2 0 00-2-2h-2a2 2 0 00-2 2v2H9a2 2 0 00-2 2v8z" />
                        </svg>
                        {{ __('Print PDF') }}
                    </a>

                    @if ($isLinked)
                        <span class="tooltip" data-tip="{{ __('Cannot edit this document because it is linked to') . ' ' . $linkedType . '.' }}">
                            <button class="btn btn-primary gap-2 btn-disabled cursor-not-allowed" disabled>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                {{ __('Edit') }}
                            </button>
                        </span>
                        <span class="tooltip" data-tip="{{ __('Cannot change status of this document because it is linked to') . ' ' . $linkedType . '.' }}">
                            <button class="btn btn-primary gap-2 btn-disabled cursor-not-allowed" disabled>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                {{ $document->approved_at ? __('Unapprove') : __('Approve') }}
                            </button>
                        </span>
                        <span class="tooltip" data-tip="{{ __('Cannot delete this document because it is linked to') . ' ' . $linkedType . '.' }}">
                            <button class="btn btn-error gap-2 btn-disabled cursor-not-allowed" disabled>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                                {{ __('Delete') }}
                            </button>
                        </span>
                    @else
                        <a href="{{ route('documents.edit', $document->id) }}" class="btn btn-primary gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            {{ __('Edit') }}
                        </a>
                        <form action="{{ route('documents.change-status', $document->id) }}" method="POST" class="inline-block">
                            @csrf
                            <button type="submit" class="btn btn-active gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                {{ $document->approved_at ? __('Unapprove') : __('Approve') }}
                            </button>
                        </form>
                        <form action="{{ route('documents.destroy', $document) }}" method="POST" class="inline-block"
                            onsubmit="return confirm('{{ __('Are you sure you want to delete this document?') }}')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-error gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                                {{ __('Delete') }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
