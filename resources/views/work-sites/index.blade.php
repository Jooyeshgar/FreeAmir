<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Work Sites') }}
        </h2>
    </x-slot>

    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="card-actions">
                @can('salary.work-sites.create')
                    <a href="{{ route('work-sites.create') }}" class="btn btn-primary">
                        {{ __('Create Work Site') }}
                    </a>
                @endcan
            </div>

            <form action="{{ route('work-sites.index') }}" method="GET">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 w-full md:w-2/5">
                    <div class="relative">
                        <input type="text" name="search" value="{{ $search }}" placeholder="{{ __('Search by name or code') }}"
                            class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 focus:ring-1 focus:ring-blue-500 focus:border-blue-500" />
                    </div>
                    <div class="flex items-center">
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 px-3 text-sm rounded-lg shadow transition-all">
                            {{ __('Search') }}
                        </button>
                    </div>
                </div>
            </form>

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
                                    <a href="{{ route('work-sites.edit', $workSite) }}" class="btn btn-sm btn-info">
                                        {{ __('Edit') }}
                                    </a>
                                @endcan
                                @can('salary.work-sites.index')
                                    <a href="{{ route('work-site-contracts.index', ['work_site_id' => $workSite->id]) }}" class="btn btn-sm btn-ghost">
                                        {{ __('Contracts') }}
                                    </a>
                                @endcan
                                @can('salary.work-sites.delete')
                                    <form action="{{ route('work-sites.destroy', $workSite) }}" method="POST" class="inline-block"
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
