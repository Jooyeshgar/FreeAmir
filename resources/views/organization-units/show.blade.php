<x-app-layout :title="$organizationUnit->name">
    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="card-title">{{ $organizationUnit->name }}</h2>
                    <div class="flex flex-wrap gap-2 mt-2">
                        @if ($organizationUnit->code)
                            <span class="badge badge-accent">{{ $organizationUnit->code }}</span>
                        @endif
                        @if ($organizationUnit->parent)
                            <a href="{{ route('hr.organization-units.show', $organizationUnit->parent) }}" class="badge badge-secondary">
                                {{ $organizationUnit->parent->name }}
                            </a>
                        @endif
                        @if ($organizationUnit->is_active)
                            <span class="badge badge-success">{{ __('Active') }}</span>
                        @else
                            <span class="badge badge-error">{{ __('Inactive') }}</span>
                        @endif
                    </div>
                </div>

                <div class="flex gap-2">
                    @can('hr.organization-units.edit')
                        <a href="{{ route('hr.organization-units.edit', $organizationUnit) }}" class="btn btn-sm btn-info">
                            {{ __('Edit') }}
                        </a>
                    @endcan
                    <a href="{{ route('hr.organization-units.index') }}" class="btn btn-sm btn-ghost">
                        {{ __('Back') }}
                    </a>
                </div>
            </div>

            @if ($organizationUnit->description)
                <p class="text-sm text-base-content/70 mt-3">{{ $organizationUnit->description }}</p>
            @endif

            <div class="divider">{{ __('Employees') }}</div>

            <div class="overflow-x-auto">
                <table class="table table-sm w-full">
                    <thead>
                        <tr>
                            <th>{{ __('Code') }}</th>
                            <th>{{ __('Full Name') }}</th>
                            <th>{{ __('Work Site') }}</th>
                            <th>{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($organizationUnit->employees as $employee)
                            <tr>
                                <td>{{ $employee->code }}</td>
                                <td>
                                    @can('hr.employees.show')
                                        <a href="{{ route('hr.employees.show', $employee) }}" class="link link-primary">
                                            {{ $employee->first_name }} {{ $employee->last_name }}
                                        </a>
                                    @else
                                        {{ $employee->first_name }} {{ $employee->last_name }}
                                    @endcan
                                </td>
                                <td>{{ $employee->workSite?->name ?? '—' }}</td>
                                <td>
                                    @if ($employee->is_active)
                                        <span class="badge badge-success badge-sm">{{ __('Active') }}</span>
                                    @else
                                        <span class="badge badge-error badge-sm">{{ __('Inactive') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-4 text-gray-500">
                                    {{ __('No employees assigned to this organization unit.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($organizationUnit->children->isNotEmpty())
                <div class="divider">{{ __('Child Units') }}</div>
                <div class="flex flex-wrap gap-2">
                    @foreach ($organizationUnit->children as $child)
                        <a href="{{ route('hr.organization-units.show', $child) }}" class="badge badge-outline">
                            {{ $child->name }}
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
