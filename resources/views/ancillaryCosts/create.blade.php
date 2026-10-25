<x-app-layout :title="__('Create Ancillary Cost')">
    <div>
        <form action="{{ isset($invoice) && $invoice ? route('invoices.ancillary-costs.store', $invoice) : route('ancillary-costs.store') }}"
            method="POST">
            @csrf
            <div class="card-body">
                <div class="flex items-center gap-1">
                    <h2 class="card-title">{{ __('Add Ancillary Cost') }}</h2>
                    <x-user-guide-link source="invoices/purchases/ancillary-costs/ancillary-costs.md" />
                </div>
                <x-show-message-bags />

                @include('ancillaryCosts.form')
            </div>
        </form>
    </div>
</x-app-layout>
