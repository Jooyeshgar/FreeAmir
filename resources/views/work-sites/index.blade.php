<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Work Sites') }}
        </h2>
    </x-slot>

    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="card-actions">
                @can('salary.work-sites.create')
                    <a href="{{ route('work-sites.create') }}" class="btn btn-primary">
                        {{ __('Create Work Site') }}
                    </a>
                @endcan
            </div>

            <table class="table w-full mt-4 overflow-auto">
                <thead>
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Code') }}</th>
                        <th>{{ __('Phone') }}</th>
                        <th>{{ __('Address') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($workSites as $workSite)
                        <tr>
                            <td>{{ $workSite->name }}</td>
                            <td>{{ $workSite->code }}</td>
                            <td>{{ $workSite->phone ?? '-' }}</td>
                            <td>{{ $workSite->address ?? '-' }}</td>
                            <td>
                                @if ($workSite->is_active)
                                    <span class="badge badge-success">{{ __('Active') }}</span>
                                @else
                                    <span class="badge badge-error">{{ __('Inactive') }}</span>
                                @endif
                            </td>
                            <td class="flex gap-2">
                                @can('salary.work-sites.edit')
                                    <a href="{{ route('work-sites.edit', $workSite) }}" class="btn btn-sm btn-info">
                                        {{ __('Edit') }}
                                    </a>
                                @endcan
                                @can('salary.work-sites.delete')
                                    <form action="{{ route('work-sites.destroy', $workSite) }}" method="POST" class="inline-block"
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
                                {{ __('No work sites found.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            {!! $workSites->links() !!}
        </div>
    </div>
</x-app-layout>
