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
                        <th class="px-2 py-2 w-1">{{ __('Index') }}</th>
                        <th class="px-4 py-2">{{ __('Type') }}</th>
                        <th class="px-4 py-2">{{ __('Invoice Number') }}</th>
                        <th class="px-4 py-2">{{ __('Date') }}</th>
                        <th class="px-4 py-2">{{ __('Customer') }}</th>
                        <th class="px-4 py-2">{{ __('Price') }}</th>
                        <th class="px-4 py-2">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($fullFormattedConflicts as $conflict)
                        <tr>
                            <td class="px-2 py-2 w-1">
                                {{ convertToFarsi($loop->iteration) }}</td>
                            <td class="px-4 py-2">
                                @if ($conflict['recursive_type'] instanceof \App\Models\Invoice)
                                    <a href="{{ route('invoices.show', $conflict['recursive_type']) }}"
                                        class="text-primary link link-hover">
                                        {{ $conflict['type'] }}
                                    </a>
                                @elseif ($conflict['recursive_type'] instanceof \App\Models\AncillaryCost)
                                    <a href="{{ route('invoices.show', $conflict['recursive_type']->invoice) }}"
                                        class="text-primary link link-hover">
                                        {{ $conflict['type'] }}
                                    </a>
                                @else
                                    <a href="{{ route('products.show', $conflict['recursive_type']) }}"
                                        class="text-primary link link-hover">
                                        {{ $conflict['type'] }}
                                    </a>
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                {{ isset($conflict['number']) ? formatDocumentNumber($conflict['number']) : '-' }}
                            </td>
                            <td class="px-4 py-2">
                                {{ isset($conflict['date']) ? formatDate($conflict['date']) : '-' }}
                            </td>
                            <td class="px-4 py-2">
                                @if (isset($conflict['customer']))
                                    <a href="{{ route('customers.show', $conflict['customer']['id']) }}"
                                        class="text-primary link link-hover">
                                        {{ $conflict['customer']['name'] }}
                                    </a>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                <span>{{ empty($conflict['price']) ? formatNumber(0) : $conflict['price'] }}</span>
                            </td>
                            <td class="px-4 py-2">
                                <span>{{ isset($conflict['status']) ? $conflict['status'] : '-' }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>

                <tfoot>
                    <tr>
                        <td class="px-4 py-2 text-left" colspan="7">
                            <form action="{{ route('invoices.groupAction', $invoice) }}" method="POST" class="inline">
                                @csrf
                                <input type="hidden" name="conflicts"
                                    value="{{ json_encode($fullFormattedConflicts) }}">
                                <span>
                                    <button type="submit" class="btn btn-primary">
                                        {{ __('Confirm') }}
                                    </button>
                                </span>
                            </form>
                        </td>
                    </tr>
                </tfoot>
            </table>

            {!! $fullFormattedConflicts->links() !!}

        </div>
    </div>
</x-app-layout>
