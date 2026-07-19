<x-app-layout :title="__('Public Holidays')">
    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="flex items-center justify-between gap-3">
                <form action="{{ route('salary.public-holidays.index') }}" method="GET" class="flex items-center gap-2">
                    <div class="w-60 [&_.input]:input-sm">
                        <x-input type="text" name="name" value="{{ request('name') }}" placeholder="{{ __('Filter by name') }}" />
                    </div>
                    <button type="submit" class="btn btn-sm btn-neutral">{{ __('Search') }}</button>
                </form>

                @can('salary.public-holidays.create')
                    <a href="{{ route('salary.public-holidays.create') }}" class="btn btn-primary btn-circle" title="{{ __('Create Public Holiday') }}">
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
                            <td>{{ formatDate($publicHoliday->date) }}</td>
                            <td>{{ $publicHoliday->name }}</td>
                            <td class="flex gap-2">
                                @can('salary.public-holidays.edit')
                                    <a href="{{ route('salary.public-holidays.edit', array_merge(['public_holiday' => $publicHoliday->id], request()->query())) }}" class="btn btn-sm btn-info">
                                        {{ __('Edit') }}
                                    </a>
                                @endcan
                                @can('salary.public-holidays.delete')
                                    <form action="{{ route('salary.public-holidays.destroy', array_merge(['public_holiday' => $publicHoliday->id], request()->query())) }}" method="POST" class="inline-block"
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
