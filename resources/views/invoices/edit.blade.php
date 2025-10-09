<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Invoice') }} #{{ formatDocumentNumber($invoice->number) }}
        </h2>
    </x-slot>

    <div class="">
        <form action="{{ route('invoices.update', $invoice) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                <h2 class="card-title">{{ __('Edit Invoice') }}</h2>
                <x-show-message-bags />

                @php($invoice_type = $invoice->invoice_type->value)
                @include('invoices.form')
            </div>
        </form>
    </div>
</x-app-layout>