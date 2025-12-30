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

            @include('invoices.conflicts.header', ['invoice' => $invoice])

            @foreach ($conflicts as $type => $conflict)
                @if ($conflict->isEmpty())
                    @continue
                @endif

                @include('invoices.conflicts.table', [
                    'conflicts' => $conflict,
                    'invoice' => $invoice,
                    'type' => $type,
                ])

                @if ($conflict->hasPages())
                    <div class="px-4 py-2 text-center">
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <a href="{{ route('invoices.conflicts.more', [
                                    'invoice' => $invoice,
                                    'type' => $type,
                                ]) }}"
                                    class="btn btn-sm btn-primary">
                                    {{ __('Show All ' . ucfirst($type)) }} ({{ $conflict->total() }})
                                </a>
                            </td>
                        </tr>
                    </div>
                @endif
            @endforeach

            @if ($allowedToResolve)
                <div class="px-4 py-2 text-left">
                    <a href="{{ route('invoices.groupAction', $invoice) }}" class="btn btn-primary">
                        {{ __('Delete All Documents') }}
                    </a>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
