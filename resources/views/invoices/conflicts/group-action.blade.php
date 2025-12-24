<x-app-layout :title="__('Conflicts')">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Conflicts') }}
        </h2>
    </x-slot>
    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <x-show-messages type="warning" message="{{ __('conflict_notice') }}" />

            @include('invoices.conflicts.invoice-section', ['invoice' => $invoice])

            @foreach ($conflicts as $conflict)
                @if ($conflict->isEmpty())
                    @continue
                @endif

                @php
                    $type =
                        $conflict === $invoicesConflicts
                            ? 'invoices'
                            : ($conflict === $ancillaryConflicts
                                ? 'ancillaryCosts'
                                : 'products');
                @endphp

                @include('invoices.conflicts.conflicts-table', [
                    'conflicts' => $conflict,
                    'invoice' => $invoice,
                    'type' => $type,
                ])

                @if ($conflict->hasPages())
                    <div class="px-4 py-2 text-center">
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <a href="{{ route('invoices.conflicts.type', ['invoice' => $invoice, 'type' => $type]) }}"
                                    class="btn btn-sm btn-primary">
                                    {{ __('Show All Invoices') }}
                                </a>
                            </td>
                        </tr>
                    </div>
                @endif
            @endforeach

            <div class="px-4 py-2 text-left">
                <form action="{{ route('invoices.groupAction', $invoice) }}" method="POST" class="inline">
                    @csrf
                    <input type="hidden" name="conflicts" value="{{ json_encode($conflicts) }}">
                    <button type="submit" class="btn btn-primary">{{ __('Confirm') }}</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
