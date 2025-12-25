<x-app-layout :title="__('Conflicts')">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Conflicts') }} - {{ ucfirst($type) }}
        </h2>
    </x-slot>

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="mb-4">
                <a href="{{ route('invoices.conflicts', $invoice) }}" class="btn btn-sm btn-primary">
                    ← {{ __('Back to All Conflicts') }}
                </a>
            </div>

            @include('invoices.conflicts.invoice-section', ['invoice' => $invoice])

            @include('invoices.conflicts.conflicts-table', [
                'conflicts' => $conflicts,
                'invoice' => $invoice,
                'type' => 'invoices',
            ])

            @if ($conflicts->hasPages())
                {{ $conflicts->links() }}
            @endif
        </div>
    </div>

</x-app-layout>
