<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Organizational Charts') }}
        </h2>
    </x-slot>
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="card-actions">
                <x-button href="{{ route('payroll.organizational_charts.create') }}" class="btn-primary">{{ __('Create Organizational Chart') }}</x-button>
            </div>
            <table class="table w-full mt-4 overflow-auto">
                <thead>
                    <tr>
                        <th class="p-2 w-40">{{ __('Name') }}</th>
                        <th class="p-2 w-40">{{ __('Supervisor') }}</th>
                        <th class="p-2 w-60">{{ __('Description') }}</th>
                        <th class="p-2 w-60">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($organizationalCharts as $chart)
                        <tr>
                            <td class="p-2"><a href="{{ route('payroll.organizational_charts.edit', $chart->id) }}">{{ $chart->name }}</a></td>
                            <td class="p-2">{{ $chart->supervisor }}</td>
                            <td class="p-2">{{ $chart->description }}</td>
                            <td class="p-2">
                                <a href="{{ route('payroll.organizational_charts.edit', $chart->id) }}" class="btn btn-sm btn-info">{{ __('Edit') }}</a>
                                <form action="{{ route('payroll.organizational_charts.destroy', $chart) }}" method="POST" class="inline-block">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-error">{{ __('Delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $organizationalCharts->links() }}
        </div>
    </div>
</x-app-layout>
