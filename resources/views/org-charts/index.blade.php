<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Organization Chart') }}
        </h2>
    </x-slot>

    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="card-actions">
                @can('org-charts.create')
                    <a href="{{ route('org-charts.create') }}" class="btn btn-primary">
                        {{ __('Create Node') }}
                    </a>
                @endcan
            </div>

            <form action="{{ route('org-charts.index') }}" method="GET">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 w-full md:w-2/5">
                    <div class="relative">
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search by title') }}"
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
                        <th>{{ __('Title') }}</th>
                        <th>{{ __('Parent') }}</th>
                        <th>{{ __('Description') }}</th>
                        <th>{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orgCharts as $orgChart)
                        <tr>
                            <td>{{ $orgChart->title }}</td>
                            <td>{{ $orgChart->parent?->title ?? '-' }}</td>
                            <td>{{ $orgChart->description ?? '-' }}</td>
                            <td class="flex gap-2">
                                @can('org-charts.show')
                                    <a href="{{ route('org-charts.show', $orgChart) }}" class="btn btn-sm btn-ghost">
                                        {{ __('View') }}
                                    </a>
                                @endcan
                                @can('org-charts.edit')
                                    <a href="{{ route('org-charts.edit', $orgChart) }}" class="btn btn-sm btn-info">
                                        {{ __('Edit') }}
                                    </a>
                                @endcan
                                @can('org-charts.delete')
                                    <form action="{{ route('org-charts.destroy', $orgChart) }}" method="POST" class="inline-block"
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
                            <td colspan="4" class="text-center py-4 text-gray-500">
                                {{ __('No organization chart nodes found.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            {!! $orgCharts->withQueryString()->links() !!}
        </div>
    </div>
</x-app-layout>
