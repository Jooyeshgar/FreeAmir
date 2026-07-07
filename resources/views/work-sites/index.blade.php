<x-app-layout :title="__('Work Sites')">
    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <form action="{{ route('salary.work-sites.index') }}" method="GET" class="flex gap-3 w-full">
                <div class="w-60 [&_.input]:input-sm">
                    <x-input type="text" name="search" value="{{ $search }}" placeholder="{{ __('Search by name or code') }}" />
                </div>
                <div>
                    <button type="submit" class="btn btn-sm btn-neutral">{{ __('Search') }}</button>
                </div>
            </form>

            <div class="card-actions">
                @can('salary.work-sites.create')
                    <a href="{{ route('salary.work-sites.create') }}" class="btn btn-primary">
                        {{ __('Create Work Site') }}
                    </a>
                @endcan
            </div>

            <table class="table w-full mt-4 overflow-auto">
                <thead>
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Code') }}</th>
                        <th>{{ __('Phone') }}</th>
                        <th>{{ __('Active') }}</th>
                        <th>{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($workSites as $workSite)
                        <tr>
                            <td>{{ $workSite->name }}</td>
                            <td>{{ $workSite->code }}</td>
                            <td>{{ $workSite->phone ?? '-' }}</td>
                            <td>
                                @if ($workSite->is_active)
                                    <span class="badge badge-success">{{ __('Yes') }}</span>
                                @else
                                    <span class="badge badge-ghost">{{ __('No') }}</span>
                                @endif
                            </td>
                            <td class="flex gap-2">
                                @can('salary.work-sites.edit')
                                    <a href="{{ route('salary.work-sites.edit', $workSite) }}" class="btn btn-sm btn-info">
                                        {{ __('Edit') }}
                                    </a>
                                @endcan
                                @can('salary.work-sites.index')
                                    <a href="{{ route('salary.work-site-contracts.index', ['work_site_id' => $workSite->id]) }}" class="btn btn-sm btn-ghost">
                                        {{ __('Contracts') }}
                                    </a>
                                @endcan
                                @can('salary.work-sites.delete')
                                    <form action="{{ route('salary.work-sites.destroy', $workSite) }}" method="POST" class="inline-block"
                                        onsubmit="return confirm('{{ __('Are you sure?') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-error">
                                            {{ __('Delete') }}
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-4 text-gray-500">
                                {{ __('No work sites found.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="mt-4">
                {{ $workSites->withQueryString()->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
