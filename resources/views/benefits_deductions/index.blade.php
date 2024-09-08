<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Benefits and Deductions') }}
        </h2>
    </x-slot>
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="card-actions">
                <x-button href="{{ route('payroll.benefits_deductions.create') }}" class="btn-primary">{{ __('Create Benefit or Deduction') }}</x-button>
            </div>
            <table class="table w-full mt-4 overflow-auto">
                <thead>
                    <tr>
                        <th class="p-2 w-40">{{ __('Name') }}</th>
                        <th class="p-2 w-40">{{ __('Type') }}</th>
                        <th class="p-2 w-40">{{ __('Calculation') }}</th>
                        <th class="p-2 w-40">{{ __('Amount') }}</th>
                        <th class="p-2 w-60">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($benefitsDeductions as $benefitDeduction)
                        <tr>
                            <td class="p-2"><a href="{{ route('payroll.benefits_deductions.edit', $benefitDeduction->id) }}">{{ $benefitDeduction->name }}</a></td>
                            <td class="p-2">{{ $benefitDeduction->type }}</td>
                            <td class="p-2">{{ $benefitDeduction->calculation }}</td>
                            <td class="p-2">{{ number_format($benefitDeduction->amount, 2) }}</td>
                            <td class="p-2">
                                <a href="{{ route('payroll.benefits_deductions.edit', $benefitDeduction->id) }}" class="btn btn-sm btn-info">{{ __('Edit') }}</a>
                                <form action="{{ route('payroll.benefits_deductions.destroy', $benefitDeduction) }}" method="POST" class="inline-block">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-error">{{ __('Delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $benefitsDeductions->links() }}
        </div>
    </div>
</x-app-layout>
