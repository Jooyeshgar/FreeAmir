<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Personnel Records') }}
        </h2>
    </x-slot>

    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="card-actions">
                <x-button href="{{ route('payroll.personnel.create') }}" class="btn-primary">
                    {{ __('Create Personnel Record') }}
                </x-button>
            </div>
            <table class="table w-full mt-4">
                <thead>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Personnel Code') }}</th>
                    <th>{{ __('Actions') }}</th>
                </thead>
                <tbody>
                    @foreach ($personnelRecords as $personnel)
                        <tr>
                            <td>{{ $personnel->first_name }} {{ $personnel->last_name }}</td>
                            <td>{{ $personnel->personnel_code }}</td>
                            <td>
                                <a href="{{ route('payroll.personnel.edit', $personnel->id) }}"
                                    class="btn btn-sm btn-info">{{ __('Edit') }}</a>
                                <form action="{{ route('payroll.personnel.destroy', $personnel) }}" method="POST"
                                    class="inline-block">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-error">{{ __('Delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{ $personnelRecords->links() }}
        </div>
    </div>
</x-app-layout>