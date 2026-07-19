<x-app-layout :title="__('Organization Chart')">
    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <form action="{{ route('hr.org-charts.index') }}" method="GET" class="flex gap-3 w-full">
                <div class="w-60 [&_.input]:input-sm">
                    <x-input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search by title') }}" />
                </div>
                <div>
                    <button type="submit" class="btn btn-sm btn-neutral">{{ __('Search') }}</button>
                </div>
            </form>

            <div class="card-actions">
                @can('hr.org-charts.create')
                    <a href="{{ route('hr.org-charts.create') }}" class="btn btn-primary">
                        {{ __('Create Node') }}
                    </a>
                @endcan
            </div>

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
                            <td>
                                @can('hr.org-charts.show')
                                    <a href="{{ route('hr.org-charts.show', $orgChart) }}">{{ $orgChart->title }}</a>
                                @else
                                    {{ $orgChart->title }}
                                @endcan
                            </td>
                            <td>{{ $orgChart->parent?->title ?? '-' }}</td>
                            <td>{{ $orgChart->description ?? '-' }}</td>
                            <td class="flex gap-2">
                                @can('hr.org-charts.edit')
                                    <a href="{{ route('hr.org-charts.edit', array_merge(['org_chart' => $orgChart->id], request()->query())) }}" class="btn btn-sm btn-info">
                                        {{ __('Edit') }}
                                    </a>
                                @endcan
                                @can('hr.org-charts.delete')
                                    <form action="{{ route('hr.org-charts.destroy', array_merge(['org_chart' => $orgChart->id], request()->query())) }}" method="POST" class="inline-block"
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
