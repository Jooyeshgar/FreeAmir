<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Workhouses') }}
        </h2>
    </x-slot>
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="card-actions">
                <x-button href="{{ route('payroll.workhouses.create') }}" class="btn-primary">{{ __('Create Workhouse') }}</x-button>
            </div>
            <table class="table w-full mt-4 overflow-auto">
                <thead>
                    <th class="p-2 w-40">{{ __('Code') }}</th>
                    <th class="p-2">{{ __('Name') }}</th>
                    <th class="p-2 w-40">{{ __('Telephone') }}</th>
                    <th class="p-2 w-60">{{ __('Action') }}</th>
                </thead>
                <tbody>
                    @foreach ($workhouses as $workhouse)
                        <tr>
                            <td class="p-2"><a href="{{ route('payroll.workhouses.edit', $workhouse->id) }}">{{ $workhouse->code }}</a>
                            </td>
                            <td class="p-2">{{ $workhouse->name }}</td>
                            <td class="p-2">{{ $workhouse->telephone }}</td>
                            <td class="p-2">
                                <a href="{{ route('payroll.workhouses.edit', $workhouse->id) }}" class="btn btn-sm btn-info">{{ __('Edit') }}</a>
                                <form action="{{ route('payroll.workhouses.destroy', $workhouse) }}" method="POST" class="inline-block">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-error">{{ __('Delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $workhouses->links() }}
        </div>
    </div>
</x-app-layout>
