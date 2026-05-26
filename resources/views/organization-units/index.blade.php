<x-app-layout :title="__('Organization Units')">
    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <form action="{{ route('hr.organization-units.index') }}" method="GET" class="flex flex-wrap gap-2 w-full">
                    <x-text-input input_class="input-sm w-80" name="search" value="{{ request('search') }}" placeholder="{{ __('Search by name or code') }}" />

                    <select name="is_active" class="select select-sm">
                        <option value="">{{ __('All Statuses') }}</option>
                        <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>{{ __('Active') }}</option>
                        <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>{{ __('Inactive') }}</option>
                    </select>

                    <button type="submit" class="btn btn-sm btn-neutral">{{ __('Search') }}</button>
                    <a href="{{ route('hr.organization-units.index') }}" class="btn btn-sm btn-ghost">{{ __('Reset') }}</a>
                </form>

                @can('hr.organization-units.create')
                    <a href="{{ route('hr.organization-units.create') }}" class="btn btn-primary">
                        {{ __('Create Organization Unit') }}
                    </a>
                @endcan
            </div>

            <div class="overflow-x-auto mt-4">
                <table class="table table-zebra w-full">
                    <thead>
                        <tr>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Code') }}</th>
                            <th>{{ __('Parent Unit') }}</th>
                            <th>{{ __('Employees') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($organizationUnits as $organizationUnit)
                            <tr>
                                <td>
                                    @can('hr.organization-units.show')
                                        <a href="{{ route('hr.organization-units.show', $organizationUnit) }}" class="link link-primary">
                                            {{ $organizationUnit->name }}
                                        </a>
                                    @else
                                        {{ $organizationUnit->name }}
                                    @endcan
                                </td>
                                <td>{{ $organizationUnit->code ?? '—' }}</td>
                                <td>{{ $organizationUnit->parent?->name ?? '—' }}</td>
                                <td>{{ formatNumber($organizationUnit->employees_count) }}</td>
                                <td>
                                    @if ($organizationUnit->is_active)
                                        <span class="badge badge-success">{{ __('Active') }}</span>
                                    @else
                                        <span class="badge badge-error">{{ __('Inactive') }}</span>
                                    @endif
                                </td>
                                <td class="flex gap-2">
                                    @can('hr.organization-units.edit')
                                        <a href="{{ route('hr.organization-units.edit', $organizationUnit) }}" class="btn btn-sm btn-info">
                                            {{ __('Edit') }}
                                        </a>
                                    @endcan
                                    @can('hr.organization-units.destroy')
                                        <form action="{{ route('hr.organization-units.destroy', $organizationUnit) }}" method="POST" class="inline-block"
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
                                <td colspan="6" class="text-center py-4 text-gray-500">
                                    {{ __('No organization units found.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {!! $organizationUnits->links() !!}
        </div>
    </div>
</x-app-layout>
