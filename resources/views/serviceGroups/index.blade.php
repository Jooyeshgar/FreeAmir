<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Service Groups') }}
        </h2>
    </x-slot>
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="card-actions">
                <a href="{{ route('service-groups.create') }}"
                    class="btn btn-primary">{{ __('Create service group') }}</a>
            </div>

            <table class="table w-full mt-4 overflow-auto">
                <thead>
                    <tr>
                        <th class="px-4 py-2">{{ __('SSTID') }}</th>
                        <th class="px-4 py-2">{{ __('Name') }}</th>
                        <th class="px-4 py-2">{{ __('VAT') }}</th>
                        <th class="px-4 py-2">{{ __('Services') }}</th>
                        <th class="px-4 py-2">{{ __('Subject') }}</th>
                        <th class="px-4 py-2">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>

                    @foreach ($serviceGroups as $serviceGroup)
                        <tr>
                            <td class="px-4 py-2">{{ $serviceGroup->sstid }}</td>
                            <td class="px-4 py-2">{{ $serviceGroup->name }}</td>
                            <td class="px-4 py-2">{{ formatNumber($serviceGroup->vat) }}%</td>
                            <td class="px-4 py-2">{{ formatNumber($serviceGroup->services->count()) }}</td>
                            <td class="px-4 py-2"><a
                                    href="{{ route('transactions.index', ['subject_id' => $serviceGroup->subject]) }}">{{ $serviceGroup->subject?->name }}</a>
                            </td>
                            <td class="px-4 py-2">
                                <a href="{{ route('service-groups.edit', $serviceGroup) }}"
                                    class="btn btn-sm btn-info">{{ __('Edit') }}</a>
                                <form action="{{ route('service-groups.destroy', $serviceGroup) }}" method="POST"
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
        </div>
    </div>
</x-app-layout>
