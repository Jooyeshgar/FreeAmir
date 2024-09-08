<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Payroll Calculation Patterns') }}
        </h2>
    </x-slot>

    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="card-actions">
                <x-button href="{{ route('payroll.payroll_patterns.create') }}" class="btn-primary">
                    {{ __('Create Payroll Pattern') }}
                </x-button>
            </div>
            <table class="table w-full mt-4">
                <thead>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Daily Wage') }}</th>
                    <th>{{ __('Actions') }}</th>
                </thead>
                <tbody>
                    @foreach ($payrollPatterns as $pattern)
                        <tr>
                            <td>{{ $pattern->name }}</td>
                            <td>{{ $pattern->daily_wage }}</td>
                            <td>
                                <a href="{{ route('payroll.payroll_patterns.edit', $pattern->id) }}"
                                    class="btn btn-sm btn-info">{{ __('Edit') }}</a>
                                <form action="{{ route('payroll.payroll_patterns.destroy', $pattern) }}" method="POST"
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

            {{ $payrollPatterns->links() }}
        </div>
    </div>
</x-app-layout>