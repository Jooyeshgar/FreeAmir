<x-app-layout :title="__('Fiscal years')">
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-1">
                    <h1 class="card-title">{{ __('Fiscal years') }}</h1>
                    <x-user-guide-link source="management/system/companies/fiscal-year.md" />
                </div>
                <div class="card-actions">
                    @can('fiscal-years.create')
                        <a href="{{ route('fiscal-years.create') }}" class="btn btn-primary">{{ __('Create Fiscal Year') }}</a>
                    @endcan
                </div>
            </div>
            <div class="overflow-auto">
                <table class="table w-full mt-4">
                    <thead>
                        <tr>
                            <th class="px-4 py-2">{{ __('Company') }}</th>
                            <th class="px-4 py-2">{{ __('Fiscal year') }}</th>
                            <th class="px-4 py-2">{{ __('Users') }}</th>
                            <th class="px-4 py-2">{{ __('Documents') }}</th>
                            <th class="px-4 py-2">{{ __('Status') }}</th>
                            <th class="px-4 py-2">{{ __('Close at') }}</th>
                            <th class="px-4 py-2">{{ __('Close by') }}</th>
                            <th class="px-4 py-2">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($years as $year)
                            <tr>
                                <td class="px-4 py-2">{{ $year->company->name }}</td>
                                <td class="px-4 py-2">{{ localizeNumber($year->year) }}</td>
                                <td class="px-4 py-2">{{ localizeNumber(number_format($year->users_count)) }}</td>
                                <td class="px-4 py-2">{{ localizeNumber(number_format($year->documents_count)) }}</td>
                                <td class="px-4 py-2">{{ $year->closed_at ? __('Closed') : __('Open') }}</td>
                                <td class="px-4 py-2">{{ formatDate($year->closed_at) }}</td>
                                <td class="px-4 py-2">{{ $year->closedBy?->name }}</td>
                                <td class="px-4 py-2">
                                    <div class="inline-flex gap-2">
                                        @can('fiscal-years.show')
                                            <a href="{{ route('fiscal-years.show', $year) }}" class="btn btn-sm">{{ __('View') }}</a>
                                        @endcan
                                        @can('fiscal-years.edit')
                                            <a href="{{ route('fiscal-years.edit', $year) }}" class="btn btn-sm btn-info">{{ __('Edit') }}</a>
                                        @endcan
                                        @can('companies.close-fiscal-year')
                                            <a href="{{ route('companies.closing-wizard', $year) }}" @class(['btn btn-sm', 'btn-warning' => !$year->closed_at, 'btn-info btn-outline' => $year->closed_at])>
                                                {{ $year->closed_at ? __('Review Fiscal Year Closing') : __('Close Fiscal Year') }}
                                            </a>
                                        @endcan
                                        @can('fiscal-years.destroy')
                                            <form action="{{ route('fiscal-years.destroy', $year) }}" method="POST" class="inline-block" onsubmit="return confirm('{{ __('Are you sure you want to delete this fiscal year?') }}');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-error">{{ __('Delete') }}</button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="py-16 text-center">{{ __('No fiscal years found.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $years->links() }}
        </div>
    </div>
</x-app-layout>
