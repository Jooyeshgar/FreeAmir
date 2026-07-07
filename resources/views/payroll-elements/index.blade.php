<x-app-layout :title="__('Payroll Elements')">
    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <form action="{{ route('salary.payroll-elements.index') }}" method="GET" class="flex flex-wrap items-center gap-2">
                <div class="w-60 [&_.input]:input-sm">
                    <x-input type="text" name="title" value="{{ request('title') }}" placeholder="{{ __('Filter by title') }}" />
                </div>
                <div>
                    <select name="category" class="select select-sm w-30">
                        <option value="">{{ __('All Categories') }}</option>
                        <option value="earning" @selected(request('category') === 'earning')>{{ __('Earning') }}</option>
                        <option value="deduction" @selected(request('category') === 'deduction')>{{ __('Deduction') }}</option>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn btn-sm btn-neutral">{{ __('Search') }}</button>
                </div>
            </form>

            <div class="card-actions">
                @can('salary.payroll-elements.create')
                    <a href="{{ route('salary.payroll-elements.create') }}" class="btn btn-primary">
                        {{ __('Create Payroll Element') }}
                    </a>
                @endcan
            </div>

            <table class="table w-full mt-4 overflow-auto">
                <thead>
                    <tr>
                        <th>{{ __('Title') }}</th>
                        <th>{{ __('System Code') }}</th>
                        <th>{{ __('Category') }}</th>
                        <th>{{ __('Calculation Type') }}</th>
                        <th>{{ __('Default Amount') }}</th>
                        <th>{{ __('Is Taxable') }}</th>
                        <th>{{ __('Is Insurable') }}</th>
                        <th>{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payrollElements as $element)
                        <tr>
                            <td>{{ $element->title }}</td>
                            <td>{{ $element->system_code }}</td>
                            <td>
                                <span class="badge {{ $element->category === 'earning' ? 'badge-success' : 'badge-error' }}">
                                    {{ $element->category === 'earning' ? __('Earning') : __('Deduction') }}
                                </span>
                            </td>
                            <td>{{ __($element->calc_type) }}</td>
                            <td>{{ $element->default_amount !== null ? formatNumber($element->default_amount) : '-' }}</td>
                            <td>
                                @if ($element->is_taxable)
                                    <span class="badge badge-warning">{{ __('Yes') }}</span>
                                @else
                                    <span class="badge badge-ghost">{{ __('No') }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($element->is_insurable)
                                    <span class="badge badge-info">{{ __('Yes') }}</span>
                                @else
                                    <span class="badge badge-ghost">{{ __('No') }}</span>
                                @endif
                            </td>
                            <td class="flex gap-2">
                                @can('salary.payroll-elements.edit')
                                    <a href="{{ route('salary.payroll-elements.edit', $element) }}" class="btn btn-sm btn-info">
                                        {{ __('Edit') }}
                                    </a>
                                @endcan
                                @can('salary.payroll-elements.delete')
                                    @unless ($element->is_system_locked)
                                        <form action="{{ route('salary.payroll-elements.destroy', $element) }}" method="POST" class="inline-block"
                                            onsubmit="return confirm('{{ __('Are you sure?') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-error">
                                                {{ __('Delete') }}
                                            </button>
                                        </form>
                                    @endunless
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-gray-500">
                                {{ __('No payroll elements found.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="mt-4">
                {{ $payrollElements->withQueryString()->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
