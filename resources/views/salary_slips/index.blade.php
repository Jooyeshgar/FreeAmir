<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Salary Slips') }}
        </h2>
    </x-slot>

    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="card-actions">
                <x-button href="{{ route('payroll.salary_slips.create') }}" class="btn-primary">
                    {{ __('Create Salary Slip') }}
                </x-button>
            </div>
            <table class="table w-full mt-4">
                <thead>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Daily Wage') }}</th>
                    <th>{{ __('Actions') }}</th>
                </thead>
                <tbody>
                    @foreach ($salarySlips as $slip)
                        <tr>
                            <td>{{ $slip->name }}</td>
                            <td>{{ $slip->daily_wage }}</td>
                            <td>
                                <a href="{{ route('payroll.salary_slips.edit', $slip->id) }}"
                                    class="btn btn-sm btn-info">{{ __('Edit') }}</a>
                                <form action="{{ route('payroll.salary_slips.destroy', $slip) }}" method="POST"
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

            {{ $salarySlips->links() }}
        </div>
    </div>
</x-app-layout>