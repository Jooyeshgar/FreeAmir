<x-app-layout :title="__('Conflicts')">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Conflicts') }}
        </h2>
    </x-slot>
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <table class="table w-full mt-4 overflow-auto">
                <thead>
                    <tr>
                        <th class="px-4 py-2">{{ __('Index') }}</th>
                        <th class="px-4 py-2">{{ __('Type') }}</th>
                        <th class="px-4 py-2">{{ __('Customer') }}</th>
                        <th class="px-4 py-2">{{ __('Price') }}</th>
                        <th class="px-4 py-2">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($fullFormattedConflicts as $conflict)
                        <tr>
                            <td class="px-4 py-2">{{ convertToFarsi($loop->iteration) }}</td>
                            <td class="px-4 py-2">
                                <a href="{{ route('invoices.show', $invoice) }}" class="text-primary link link-hover">
                                    {{ $conflict['type'] }}
                                </a>
                            </td>
                            <td class="px-4 py-2">
                                <a href="{{ route('customers.show', $conflict['customer']['id']) }}"
                                    class="text-primary link link-hover">
                                    {{ $conflict['customer']['name'] }}
                                </a>
                            </td>
                            <td class="px-4 py-2">
                                <span>{{ $conflict['price'] }}</span>
                            </td>
                            <td class="px-4 py-2">
                                <span>{{ $conflict['status'] }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <button class="btn btn-primary" onclick="window.location='{{ route('invoices.index') }}'">
                {{ __('Confirm') }}
            </button>

            {!! $fullFormattedConflicts->links() !!}

        </div>
    </div>
</x-app-layout>
