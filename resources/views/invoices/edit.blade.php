<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Invoice') }} #{{ formatDocumentNumber($invoice->number) }}
            {{ $isServiceBuy ? __('Edit') . ' ' . __('Service Buy Invoice') : __('Edit Invoice') }}
        </h2>
    </x-slot>

    <div class="">
        <form action="{{ route('invoices.update', $invoice) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                <h2 class="card-title">
                    {{ $isServiceBuy ? __('Edit') . ' ' . __('Service Buy Invoice') : __('Edit Invoice') . ' ' . $invoice->invoice_type->label() }}
                </h2>
                <x-show-message-bags />

                @include('invoices.form')
            </div>
        </form>
    </div>
</x-app-layout>
