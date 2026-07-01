<article class="card border border-base-300 bg-base-100/90 shadow-sm">
    <div class="card-body p-4">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
                <h2 class="card-title text-base">{{ __('Recent Accounting Documents') }}</h2>
                <p class="text-xs text-base-content/55">{{ __('Pending documents are shown first for daily follow-up.') }}</p>
            </div>
            <a href="{{ route('documents.index') }}" class="btn btn-xs btn-ghost">{{ __('View All') }}</a>
        </div>

        <div class="mt-3 overflow-x-auto">
            <table class="table table-zebra table-sm">
                <thead>
                    <tr>
                        <th>{{ __('Number') }}</th>
                        <th>{{ __('Title') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentDocuments as $document)
                        <tr>
                            <td>
                                @can('documents.show')
                                    <a href="{{ route('documents.show', $document) }}" class="link link-hover">
                                        {{ localizeNumber($document->number) }}
                                    </a>
                                @else
                                    {{ localizeNumber($document->number) }}
                                @endcan
                            </td>
                            <td class="max-w-52 truncate">{{ $document->title }}</td>
                            <td>{{ formatDate($document->date) }}</td>
                            <td>
                                @if ($document->approved_at)
                                    <span class="badge badge-success badge-outline badge-sm">{{ __('Approved') }}</span>
                                @else
                                    <span class="badge badge-warning badge-sm">{{ __('Pending') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-6 text-center text-sm text-base-content/55">{{ __('No documents found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</article>
