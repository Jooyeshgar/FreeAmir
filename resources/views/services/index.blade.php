<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Services') }}
        </h2>
    </x-slot>
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="card-actions">
                <a href="{{ route('services.create') }}" class="btn btn-primary">{{ __('Create service') }}</a>
            </div>

            <form action="{{ route('services.index') }}" method="GET">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 w-full md:w-2/5">
                    <div class="relative">
                        <span class="absolute inset-y-0 left-2 flex items-center text-gray-400 text-sm">
                            <i class="fa-solid fa-box"></i>
                        </span>
                        <input type="text" name="name" value="{{ request('name') }}"
                            placeholder="{{ __('Service Name') }}"
                            class="w-full pl-8 pr-2 py-2 text-sm rounded-lg border border-gray-300 focus:ring-1 focus:ring-blue-500 focus:border-blue-500" />
                    </div>

                    <div class="relative">
                        <span class="absolute inset-y-0 left-2 flex items-center text-gray-400 text-sm">
                            <i class="fa-solid fa-layer-group"></i>
                        </span>
                        <input type="text" name="group_name" value="{{ request('group_name') }}"
                            placeholder="{{ __('Service Group Name') }}"
                            class="w-full pl-8 pr-2 py-2 text-sm rounded-lg border border-gray-300 focus:ring-1 focus:ring-blue-500 focus:border-blue-500" />
                    </div>

                    <div class="flex items-center">
                        <button type="submit"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 px-3 text-sm rounded-lg shadow transition-all">
                            <i class="fa-solid fa-magnifying-glass mr-1"></i>
                            {{ __('Search') }}
                        </button>
                    </div>
                </div>
            </form>

            <table class="table w-full mt-4 overflow-auto">
                <thead>
                    <tr>
                        <th class="px-4 py-2">{{ __('Service Code') }}</th>
                        <th class="px-4 py-2">{{ __('Name') }}</th>
                        <th class="px-4 py-2">{{ __('Sell price') }} ({{ __(config('amir.currency')) ?? __('Rial') }})
                        </th>
                        <th class="px-4 py-2">{{ __('VAT') }} ({{ __(config('amir.currency')) ?? __('Rial') }})
                        </th>
                        <th class="px-4 py-2">{{ __('Service group') }}</th>
                        <th class="px-4 py-2">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>

                    @foreach ($services as $service)
                        <tr>
                            <td class="px-4 py-2">{{ formatNumber($service->code) }}</td>
                            <td class="px-4 py-2">
                                <a href="{{ route('services.show', $service) }}" class="text-primary">
                                    {{ $service->name }}</a>
                            </td>
                            <td class="px-4 py-2">{{ formatNumber($service->selling_price) }}</td>
                            <td class="px-4 py-2">{{ formatNumber($service->vat) }}%</td>
                            <td class="px-4 py-2">{{ $service->serviceGroup ? $service->serviceGroup->name : '' }}</td>
                            <td class="px-4 py-2">
                                <a href="{{ route('services.edit', $service) }}"
                                    class="btn btn-sm btn-info">{{ __('Edit') }}</a>
                                @if ($service->invoiceItems()->exists())
                                    <span class="tooltip"
                                        data-tip="{{ __('Cannot delete service that is used in invoice items') }}">
                                        <button class="btn btn-sm btn-info btn-disabled cursor-not-allowed" disabled
                                            title="{{ __('Cannot delete service that is used in invoice items') }}">{{ __('Delete') }}</button>
                                    </span>
                                @else
                                    <form action="{{ route('services.destroy', $service) }}" method="POST"
                                        class="inline-block">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="btn btn-sm btn-error">{{ __('Delete') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {!! $services->withQueryString()->links() !!}
        </div>
    </div>
</x-app-layout>
