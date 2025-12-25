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

            @include('invoices.conflicts.table', [
                'conflicts' => $conflicts,
                'invoice' => $invoice,
                'type' => $type,
            ])

            <div class="mb-4">
                <a href="{{ route('invoices.conflicts', $invoice) }}" class="btn btn-sm btn-primary">
                    ← {{ __('Back to All Conflicts') }}
                </a>
            </div>

            @if ($conflicts->hasPages())
                {{ $conflicts->links() }}
            @endif
        </div>
    </div>
</x-app-layout>
