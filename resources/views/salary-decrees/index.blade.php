<x-app-layout :title="__('Salary Decrees')">
    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="flex items-center justify-between gap-3">
                <form action="{{ route('salary.salary-decrees.index') }}" method="GET" class="flex items-center gap-2 flex-wrap w-3/4">
                    <div class="w-60 [&_.input]:input-sm">
                        <x-input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Filter by name') }}" />
                    </div>

                    <select name="employee_id" class="select select-sm w-60">
                        <option value="">— {{ __('All Employees') }} —</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}" {{ request('employee_id') == $employee->id ? 'selected' : '' }}>
                                {{ $employee->first_name }} {{ $employee->last_name }}
                            </option>
                        @endforeach
                    </select>

                    <button type="submit" class="btn btn-sm btn-neutral">{{ __('Search') }}</button>
                </form>

                @can('salary.salary-decrees.create')
                    <a href="{{ route('salary.salary-decrees.create') }}" class="btn btn-primary btn-circle" title="{{ __('Create Salary Decree') }}">
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
                            <td>{{ formatDate($decree->start_date) }}</td>
                            <td>{{ $decree->end_date ? formatDate($decree->end_date) : '—' }}</td>
                            <td>
                                @if ($decree->is_active)
                                    <span class="badge badge-success">{{ __('Active') }}</span>
                                @else
                                    <span class="badge badge-ghost">{{ __('Inactive') }}</span>
                                @endif
                            </td>
                            <td class="flex gap-2">
                                @can('salary.salary-decrees.edit')
                                    <a href="{{ route('salary.salary-decrees.edit', array_merge(['salary_decree' => $decree->id], request()->query())) }}" class="btn btn-sm btn-info">
                                        {{ __('Edit') }}
                                    </a>
                                @endcan
                                @can('salary.salary-decrees.delete')
                                    <form action="{{ route('salary.salary-decrees.destroy', array_merge(['salary_decree' => $decree->id], request()->query())) }}" method="POST" class="inline-block mb-0"
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
