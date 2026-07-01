<article class="card border border-base-300 bg-base-100/90 shadow-sm">
    <div class="card-body p-4">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
                <h2 class="card-title text-base">{{ __('Recent Customers') }}</h2>
                <p class="text-xs text-base-content/55">{{ __('Marked customers and recent updates appear first.') }}</p>
            </div>
            <a href="{{ route('customers.index') }}" class="btn btn-xs btn-ghost">{{ __('View All') }}</a>
        </div>

        <div class="mt-3 overflow-x-auto">
            <table class="table table-zebra table-sm">
                <thead>
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Mobile') }}</th>
                        <th>{{ __('Group') }}</th>
                        <th>{{ __('Priority') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentCustomers as $customer)
                        <tr>
                            <td>
                                @can('customers.show')
                                    <a href="{{ route('customers.show', $customer) }}" class="link link-hover">
                                        {{ $customer->name }}
                                    </a>
                                @else
                                    {{ $customer->name }}
                                @endcan
                            </td>
                            <td>{{ $customer->mobile ?: '—' }}</td>
                            <td>{{ $customer->group?->name ?? '—' }}</td>
                            <td>
                                @if ($customer->marked)
                                    <span class="badge badge-warning badge-sm">{{ __('Follow up') }}</span>
                                @else
                                    <span class="badge badge-ghost badge-sm">{{ __('Recent') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-6 text-center text-sm text-base-content/55">{{ __('No customers found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</article>
