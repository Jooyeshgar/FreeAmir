<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Public Holidays') }}
        </h2>
    </x-slot>

    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="flex items-center justify-between gap-3">
                <form action="{{ route('public-holidays.index') }}" method="GET" class="flex items-center gap-2">
                    <input type="text" name="name" value="{{ request('name') }}" placeholder="{{ __('Filter by name') }}"
                        class="px-3 py-2 text-sm rounded-lg border border-gray-300 focus:ring-1 focus:ring-blue-500 focus:border-blue-500" />
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-3 text-sm rounded-lg shadow transition-all">
                        {{ __('Search') }}
                    </button>
                </form>

                @can('salary.public-holidays.create')
                    <a href="{{ route('public-holidays.create') }}" class="btn btn-primary btn-circle" title="{{ __('Create Public Holiday') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                    </a>
                @endcan
            </div>

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
