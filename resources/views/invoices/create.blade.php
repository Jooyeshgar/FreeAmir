<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create Invoice') }}
        </h2>
    </x-slot>

    <div class="">
        <form action="{{ route('invoices.store') }}" method="POST">
            @csrf
            <div class="card-body">
                <h2 class="card-title">{{ __('Add Invoice') }}</h2>
                <x-show-message-bags />

                @php($invoice = $invoice ?? new \App\Models\Invoice())
                @include('invoices.form')
            </div>
        </form>
    </div>
</x-app-layout>
