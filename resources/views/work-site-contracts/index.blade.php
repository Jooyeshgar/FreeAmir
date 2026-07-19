<x-app-layout :title="__('Work Site Contracts')">
    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <form action="{{ route('salary.work-site-contracts.index') }}" method="GET" class="flex gap-3 w-full">
                <div class="w-60 [&_.input]:input-sm">
                    <x-input type="text" name="search" value="{{ $search }}" placeholder="{{ __('Search by name or code') }}" />
                </div>
                <div>
                    <button type="submit" class="btn btn-sm btn-neutral">{{ __('Search') }}</button>
                </div>
            </form>

            <div class="card-actions">
                @can('salary.work-site-contracts.create')
                    <a href="{{ route('salary.work-site-contracts.create') }}" class="btn btn-primary">
                        {{ __('Create Work Site Contract') }}
                    </a>
                @endcan
            </div>

            <table class="table w-full mt-4 overflow-auto">
                <thead>
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Code') }}</th>
                        <th>{{ __('Work Site') }}</th>
                        <th>{{ __('Active') }}</th>
                        <th>{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($contracts as $contract)
                        <tr>
                            <td>{{ $contract->name }}</td>
                            <td>{{ $contract->code }}</td>
                            <td>{{ $contract->workSites?->name ?? '-' }}</td>
                            <td>
                                @if ($contract->is_active)
                                    <span class="badge badge-success">{{ __('Yes') }}</span>
                                @else
                                    <span class="badge badge-ghost">{{ __('No') }}</span>
                                @endif
                            </td>
                            <td class="flex gap-2">
                                @can('salary.work-site-contracts.edit')
                                    <a href="{{ route('salary.work-site-contracts.edit', array_merge(['work_site_contract' => $contract->id], request()->query())) }}" class="btn btn-sm btn-info">
                                        {{ __('Edit') }}
                                    </a>
                                @endcan
                                @can('salary.work-site-contracts.delete')
                                    <form action="{{ route('salary.work-site-contracts.destroy', array_merge(['work_site_contract' => $contract->id], request()->query())) }}" method="POST" class="inline-block"
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
                            <td colspan="5" class="text-center py-4 text-gray-500">
                                {{ __('No work site contracts found.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="mt-4">
                {{ $contracts->withQueryString()->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
