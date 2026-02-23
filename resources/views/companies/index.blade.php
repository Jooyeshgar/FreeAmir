<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Companies') }}
        </h2>
    </x-slot>
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="card-actions">
                <a href="{{ route('companies.create') }}" class="btn btn-primary">{{ __('Create Company') }}</a>
            </div>
            <table class="table w-full mt-4 overflow-auto">
                <thead>
                    <tr>
                        <th class="px-4 py-2">{{ __('Name') }}</th>
                        <th class="px-4 py-2">{{ __('Year') }}</th>
                        <th class="px-4 py-2">{{ __('Economical Code') }}</th>
                        <th class="px-4 py-2">{{ __('Address') }}</th>
                        <th class="px-4 py-2">{{ __('Currency') }}</th>
                        <th class="px-4 py-2">{{ __('Close at') }}</th>
                        <th class="px-4 py-2">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($companies as $company)
                        <tr>
                            <td class="px-4 py-2">{{ $company->name }}</td>
                            <td class="px-4 py-2">{{ $company->fiscal_year }}</td>
                            <td class="px-4 py-2">{{ $company->economical_code }}</td>
                            <td class="px-4 py-2">{{ $company->address }}</td>
                            <td class="px-4 py-2">{{ $company->currency }}</td>
                            <td class="px-4 py-2">{{ $company->closed_at }}</td>
                            <td class="px-4 py-2">
                                <a href="{{ route('companies.edit', $company) }}" class="btn btn-sm btn-info">{{ __('Edit') }}</a>
                                <form action="{{ route('companies.close-fiscal-year', $company) }}" method="POST" class="inline-block"
                                    onsubmit="return confirm('{{ __('Are you sure you want to close the fiscal year for this company?') }}');">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-warning">{{ __('Close Fiscal Year') }}</button>
                                </form>
                                <form action="{{ route('companies.destroy', $company) }}" method="POST" class="inline-block" 
                                    onsubmit="return confirm('{{ __('Are you sure you want to delete this company?') }}');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-error">{{ __('Delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {!! $companies->links() !!}
        </div>
    </div>
</x-app-layout>
