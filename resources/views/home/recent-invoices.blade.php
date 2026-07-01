<article class="card border border-base-300 bg-base-100/90 shadow-sm">
    <div class="card-body p-4">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
                <h2 class="card-title text-base">{{ __('Recent Invoices') }}</h2>
                <p class="text-xs text-base-content/55">{{ __('Draft, pending, and partially paid invoices are prioritized.') }}</p>
            </div>
            <a href="{{ route('invoices.index') }}" class="btn btn-xs btn-ghost">{{ __('View All') }}</a>
        </div>

        <div class="mt-3 overflow-x-auto">
            <table class="table table-zebra table-sm">
                <thead>
                    <tr>
                        <th>{{ __('Number') }}</th>
                        <th>{{ __('Customer') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentInvoices as $invoice)
                        <tr>
                            <td>
                                @can('invoices.show')
                                    <a href="{{ route('invoices.show', $invoice) }}" class="link link-hover">
                                        {{ localizeNumber($invoice->number) }}
                                    </a>
                                @else
                                    {{ localizeNumber($invoice->number) }}
                                @endcan
                            </td>
                            <td class="max-w-44 truncate">{{ $invoice->customer?->name ?? '—' }}</td>
                            <td>{{ $invoice->invoice_type?->label() }}</td>
                            <td><span class="badge badge-info badge-outline badge-sm">{{ $invoice->status?->label() }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-6 text-center text-sm text-base-content/55">{{ __('No invoices found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</article>
