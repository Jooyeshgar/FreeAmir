<x-app-layout :title="__('Documents')">
    <x-show-message-bags />

    {{-- Page Header --}}
    <div class="flex flex-wrap items-center justify-between gap-4 px-1 pb-5">
        <div class="min-w-48">
            <h1 class="text-xl font-bold text-base-content">{{ __('Documents') }}</h1>
            <p class="text-sm text-base-content/50 mt-0.5">{{ __('Manage your accounting documents') }}</p>
        </div>

        <div class="flex flex-wrap items-center justify-start gap-2">
            <a href="{{ route('documents.create') }}" class="btn btn-primary btn-sm gap-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                {{ __('Create Document') }}
            </a>
            @can('documents.approve')
                <form action="{{ route('documents.approve-all') }}" method="POST" class="inline-block" id="approve-all-form">
                    @csrf
                    <button type="submit" class="btn btn-success btn-sm">
                        {{ __('Approve All') }}
                    </button>
                </form>
            @endcan
            @can('documents.export')
                <a href="{{ route('documents.export') }}" class="btn btn-secondary btn-sm gap-1.5">{{ __('Export CSV') }}</a>
            @endcan
            @can('documents.import')
                <a href="{{ route('documents.import') }}" class="btn btn-accent btn-sm gap-1.5">{{ __('Import CSV') }}</a>
            @endcan
        </div>
    </div>

    @php $status = request('status') ?? 'all'; @endphp

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body p-0">
            {{-- Card Header: title + filters --}}
            <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-base-200">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-base font-bold text-base-content">
                        @switch($status)
                            @case('approved')
                                {{ __('Approved Documents') }}
                                @break
                            @case('unapproved')
                                {{ __('Unapproved Documents') }}
                                @break
                            @default
                                {{ __('Document List') }}
                        @endswitch
                    </h2>
                    <span class="badge badge-ghost">
                        {{ localizeNumber($documents->total()) }} {{ __('records') }}
                    </span>
                    <a href="{{ route('documents.index', array_merge(request()->except('page'), ['status' => 'approved'])) }}"
                        class="badge {{ $status === 'approved' ? 'badge-success badge-outline' : 'badge-ghost' }}">
                        {{ __('Approved') }}: {{ localizeNumber($approvedDocumentsNumber) }}
                    </a>
                    <a href="{{ route('documents.index', array_merge(request()->except('page'), ['status' => 'unapproved'])) }}"
                        class="badge {{ $status === 'unapproved' ? 'badge-error badge-outline' : 'badge-ghost' }}">
                        {{ __('Not approved') }}: {{ localizeNumber($unapprovedDocumentsNumber) }}
                    </a>
                </div>

                <form action="{{ route('documents.index') }}" method="GET" class="flex flex-wrap items-center gap-2" dir="ltr">
                    <div class="relative w-32 max-w-full [&_.input]:input-sm" dir="rtl">
                        <x-input type="text" name="number" value="{{ request('number') }}" placeholder="{{ __('Doc Number') }}" />
                    </div>

                    <div class="w-36 [&_.input]:input-sm" dir="rtl">
                        <x-date-picker name="date" placeholder="{{ __('date') }}" value="{{ request('date') }}" class="datePicker" />
                    </div>

                    <div class="relative w-56 max-w-full [&_.input]:input-sm" dir="rtl">
                        <x-input type="text" name="text" value="{{ request('text') }}" placeholder="{{ __('Search by document title or transaction description') }}" />
                    </div>

                    <select name="status" class="select select-sm w-40" dir="rtl" onchange="this.form.submit()">
                        <option value="all" @selected($status === 'all')>{{ __('All Documents') }}</option>
                        <option value="approved" @selected($status === 'approved')>{{ __('Approved') }}</option>
                        <option value="unapproved" @selected($status === 'unapproved')>{{ __('Not approved') }}</option>
                    </select>

                    <button type="submit" class="btn btn-sm btn-primary gap-1.5" dir="rtl">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15z" />
                        </svg>
                        {{ __('Search') }}
                    </button>
                </form>
            </div>

            {{-- Table --}}
            @if ($documents->count())
            <table class="table w-full overflow-auto">
                <thead>
                    <tr>
                        <th class="p-2 w-12">{{ __('Doc Number') }}</th>
                        <th class="p-2">{{ __('Title') }}</th>
                        <th class="p-2 w-40">{{ __('Date') }}</th>
                        <th class="p-2 w-40">{{ __('Relation') }}</th>
                        <th class="p-2 w-40">{{ __('Approve date') }}</th>
                        <th class="p-2 w-40">{{ __('Approved By') }}</th>
                        <th class="p-2 w-60">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($documents as $document)
                        @php
                            $isBalance = $document->transactions->sum('value') !== 0.0;
                        @endphp
                        <tr class="{{ $isBalance ? 'text-red-500' : ($document->approved_at ? '' : 'text-gray-500') }}"
                            title="{{ $isBalance ? formatNumber($document->transactions->sum('value')) : '' }}" >
                            <td class="p-2">
                                <a href="{{ route('documents.show', $document->id) }}">
                                    {{ formatDocumentNumber($document->number) }}
                                </a>
                            </td>

                            <td class="p-2">
                                {{ $document->title ?? $document->transactions->first()?->desc . ' ...' }}
                            </td>

                            <td class="p-2">
                                {{ formatDate($document->date) }}
                            </td>

                            <td class="p-2">
                                @php
                                    $documentableRoute = match (true) {
                                        $document->documentable instanceof \App\Models\Invoice => [
                                            'name' => 'invoices.show',
                                            'params' => $document->documentable,
                                        ],
                                        $document->documentable instanceof \App\Models\AncillaryCost => [
                                            'name' => 'invoices.ancillary-costs.show',
                                            'params' => [$document->documentable->invoice_id ?? $document->documentable->invoice?->id, $document->documentable],
                                        ],
                                        default => null,
                                    };
                                @endphp
                                @if ($document->documentable && $documentableRoute)
                                    <a href="{{ route($documentableRoute['name'], $documentableRoute['params']) }}" class="link link-hover">
                                        {{ __(class_basename($document->documentable_type)) }} {{ localizeNumber($document->documentable->number) }}
                                    </a>
                                @endif
                            </td>
                            <td class="p-2">{{ formatDate($document->approved_at) }}</td>
                            <td class="p-2">{{ $document->approver?->name }}</td>
                            <td class="p-2">
                                <div class="flex gap-2">
                                    <a href="{{ route('documents.show', $document->id) }}" class="btn btn-sm btn-info btn-square" title="{{ __('View') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </a>
                                    @if ($document->documentable)
                                        <span class="tooltip"
                                            data-tip="{{ __('Cannot edit this document because it is linked to') . ' ' . __(class_basename($document->documentable_type)) . '.' }}">
                                            <button class="btn btn-sm btn-info btn-square btn-disabled cursor-not-allowed" disabled
                                                title="{{ __('Cannot edit this document because it is linked to another record.') }}">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                        </span>
                                    @else
                                        <a href="{{ route('documents.edit', $document->id) }}" class="btn btn-sm btn-warning btn-square" title="{{ __('Edit') }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </a>
                                    @endif

                                    @if ($document->documentable)
                                        <span class="tooltip" data-tip="{{ __('Cannot change status of this document because it created automatically.') }}">
                                            <button class="btn btn-sm btn-error btn-square btn-disabled cursor-not-allowed"
                                                title="{{ __('Cannot change status of this document because it created automatically.') }}">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            </button>
                                        </span>
                                    @else
                                        <form action="{{ route('documents.change-status', $document->id) }}" method="POST" class="inline-block">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-square {{ $document->approved_at ? 'btn-error' : 'btn-success' }}"
                                                title="{{ $document->approved_at ? __('Unapprove') : __('Approve') }}">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            </button>
                                        </form>
                                    @endif

                                    <a href="{{ route('documents.duplicate', $document->id) }}" class="btn btn-sm btn-success btn-square"
                                        title="{{ __('Duplicate') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                    </a>

                                    @if ($document->documentable)
                                        <span class="tooltip"
                                            data-tip="{{ __('Cannot delete this document because it is linked to') . ' ' . __(class_basename($document->documentable_type)) . '.' }}">
                                            <button class="btn btn-sm btn-error btn-square btn-disabled cursor-not-allowed" disabled
                                                title="{{ __('Cannot delete this document because it is linked to another record.') }}">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </span>
                                    @elseif (!$document->approved_at)
                                        <form action="{{ route('documents.destroy', $document) }}" method="POST" class="inline-block delete-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-error btn-square" title="{{ __('Delete') }}">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </form>
                                    @else
                                        <span class="tooltip"
                                            data-tip="{{ __('Cannot delete this document because it is approved') . ' ' . __(class_basename($document->documentable_type)) . '.' }}">
                                            <button class="btn btn-sm btn-error btn-square btn-disabled cursor-not-allowed" disabled
                                                title="{{ __('Cannot delete this document because it is approved.') }}">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </span>
                                    @endif

                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @else
                <div class="flex flex-col items-center justify-center py-16 text-base-content/35">
                    <svg xmlns="http://www.w3.org/2000/svg" class="mb-4 h-16 w-16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    <p class="text-base font-medium">{{ __('No documents found.') }}</p>
                    <p class="mt-1 text-sm text-base-content/30">{{ __('Try adjusting your search filters.') }}</p>
                </div>
            @endif

            {{-- Pagination --}}
            @if ($documents->hasPages())
                <div class="px-5 py-4 border-t border-base-200">
                    {{ $documents->withQueryString()->links() }}
                </div>
            @endif
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const deleteForms = document.querySelectorAll('.delete-form');

            deleteForms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();

                    if (confirm('{{ __('Are you sure you want to delete this document?') }}')) {
                        this.submit();
                    }
                });
            });

            const approveAllForm = document.getElementById('approve-all-form');
            if (approveAllForm) {
                approveAllForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    if (confirm('{{ __('Are you sure you want to approve all unapproved documents?') }}')) {
                        this.submit();
                    }
                });
            }
        });
    </script>
</x-app-layout>
