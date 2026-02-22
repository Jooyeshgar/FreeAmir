<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Public Holidays') }}
        </h2>
    </x-slot>

    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="card-actions">
                @can('salary.public-holidays.create')
                    <a href="{{ route('public-holidays.create') }}" class="btn btn-primary">
                        {{ __('Create Public Holiday') }}
                    </a>
                @endcan
            </div>

            <form action="{{ route('public-holidays.index') }}" method="GET">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 w-full md:w-2/5">
                    <div class="relative">
                        <input type="text" name="name" value="{{ request('name') }}" placeholder="{{ __('Filter by name') }}"
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
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($publicHolidays as $publicHoliday)
                        <tr>
                            <td>{{ $publicHoliday->date->format('Y-m-d') }}</td>
                            <td>{{ $publicHoliday->name }}</td>
                            <td class="flex gap-2">
                                @can('salary.public-holidays.edit')
                                    <a href="{{ route('public-holidays.edit', $publicHoliday) }}" class="btn btn-sm btn-info">
                                        {{ __('Edit') }}
                                    </a>
                                @endcan
                                @can('salary.public-holidays.delete')
                                    <form action="{{ route('public-holidays.destroy', $publicHoliday) }}" method="POST" class="inline-block"
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
                            <td colspan="3" class="text-center py-4 text-gray-500">
                                {{ __('No public holidays found.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            {!! $publicHolidays->withQueryString()->links() !!}
        </div>
    </div>
</x-app-layout>
