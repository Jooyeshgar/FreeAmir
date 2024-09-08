<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Contracts') }}
        </h2>
    </x-slot>
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="card-actions">
                <x-button href="{{ route('payroll.contracts.create') }}" class="btn-primary">{{ __('Create Contract') }}</x-button>
            </div>
            <table class="table w-full mt-4 overflow-auto">
                <thead>
                    <tr>
                        <th class="p-2 w-40">{{ __('Name') }}</th>
                        <th class="p-2 w-40">{{ __('Row') }}</th>
                        <th class="p-2 w-60">{{ __('Description') }}</th>
                        <th class="p-2 w-60">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($contracts as $contract)
                        <tr>
                            <td class="p-2"><a href="{{ route('payroll.contracts.edit', $contract->id) }}">{{ $contract->name }}</a></td>
                            <td class="p-2">{{ $contract->row }}</td>
                            <td class="p-2">{{ $contract->description }}</td>
                            <td class="p-2">
                                <a href="{{ route('payroll.contracts.edit', $contract->id) }}" class="btn btn-sm btn-info">{{ __('Edit') }}</a>
                                <form action="{{ route('payroll.contracts.destroy', $contract) }}" method="POST" class="inline-block">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-error">{{ __('Delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $contracts->links() }}
        </div>
    </div>
</x-app-layout>
