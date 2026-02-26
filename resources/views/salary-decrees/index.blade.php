<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Salary Decrees') }}
        </h2>
    </x-slot>

    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="flex items-center justify-between gap-3">
                <form action="{{ route('salary-decrees.index') }}" method="GET" class="flex items-center gap-2 flex-wrap">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Filter by name') }}"
                        class="px-3 py-2 text-sm rounded-lg border border-gray-300 focus:ring-1 focus:ring-blue-500 focus:border-blue-500" />

                    <select name="employee_id" class="px-3 py-2 text-sm rounded-lg border border-gray-300 focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">— {{ __('All Employees') }} —</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}" {{ request('employee_id') == $employee->id ? 'selected' : '' }}>
                                {{ $employee->first_name }} {{ $employee->last_name }}
                            </option>
                        @endforeach
                    </select>

                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-3 text-sm rounded-lg shadow transition-all">
                        {{ __('Search') }}
                    </button>
                </form>

                @can('salary.salary-decrees.create')
                    <a href="{{ route('salary-decrees.create') }}" class="btn btn-primary btn-circle" title="{{ __('Create Salary Decree') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                    </a>
                @endcan
            </div>

            <table class="table w-full mt-4 overflow-auto">
                <thead>
                    <tr>
                        <th>{{ __('Employee') }}</th>
                        <th>{{ __('Decree Name') }}</th>
                        <th>{{ __('Contract Type') }}</th>
                        <th>{{ __('Start Date') }}</th>
                        <th>{{ __('End Date') }}</th>
                        <th>{{ __('Active') }}</th>
                        <th>{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($decrees as $decree)
                        <tr>
                            <td>{{ $decree->employee->first_name }} {{ $decree->employee->last_name }}</td>
                            <td>{{ $decree->name ?? '—' }}</td>
                            <td>{{ $decree->contract_type ? __($decree->contract_type) : '—' }}</td>
                            <td>{{ $decree->start_date->format('Y-m-d') }}</td>
                            <td>{{ $decree->end_date?->format('Y-m-d') ?? '—' }}</td>
                            <td>
                                @if ($decree->is_active)
                                    <span class="badge badge-success">{{ __('Active') }}</span>
                                @else
                                    <span class="badge badge-ghost">{{ __('Inactive') }}</span>
                                @endif
                            </td>
                            <td class="flex gap-2">
                                @can('salary.salary-decrees.edit')
                                    <a href="{{ route('salary-decrees.edit', $decree) }}" class="btn btn-sm btn-info">
                                        {{ __('Edit') }}
                                    </a>
                                @endcan
                                @can('salary.salary-decrees.delete')
                                    <form action="{{ route('salary-decrees.destroy', $decree) }}" method="POST" class="inline-block"
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
                            <td colspan="7" class="text-center py-4 text-gray-500">
                                {{ __('No salary decrees found.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            {!! $decrees->withQueryString()->links() !!}
        </div>
    </div>
</x-app-layout>
