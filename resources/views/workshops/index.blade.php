<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Workshops') }}
        </h2>
    </x-slot>
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="card-actions">
                <x-button href="{{ route('payroll.workshops.create') }}" class="btn-primary">{{ __('Create Workshop') }}</x-button>
            </div>
            <table class="table w-full mt-4 overflow-auto">
                <thead>
                    <th class="p-2 w-40">{{ __('Code') }}</th>
                    <th class="p-2">{{ __('Name') }}</th>
                    <th class="p-2 w-40">{{ __('Telephone') }}</th>
                    <th class="p-2 w-60">{{ __('Action') }}</th>
                </thead>
                <tbody>
                    @foreach ($workshops as $workshop)
                        <tr>
                            <td class="p-2"><a href="{{ route('payroll.workshops.edit', $workshop->id) }}">{{ $workshop->code }}</a>
                            </td>
                            <td class="p-2">{{ $workshop->name }}</td>
                            <td class="p-2">{{ $workshop->telephone }}</td>
                            <td class="p-2">
                                <a href="{{ route('payroll.workshops.edit', $workshop->id) }}" class="btn btn-sm btn-info">{{ __('Edit') }}</a>
                                <form action="{{ route('payroll.workshops.destroy', $workshop) }}" method="POST" class="inline-block">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-error">{{ __('Delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $workshops->links() }}
        </div>
    </div>
</x-app-layout>
