<x-app-layout :title="__('Edit Ancillary Cost')">
    <div>
        <form action="{{ isset($invoice) && $invoice ? route('invoices.ancillary-costs.update', [$invoice, $ancillaryCost]) : route('ancillary-costs.update', $ancillaryCost) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                <div class="flex items-center gap-1">
                    <div class="card-title">{{ __('Edit Ancillary Cost') }}</div>
                    <x-user-guide-link source="invoices/purchases/ancillary-costs/ancillary-costs.md" />
                </div>
                <x-show-message-bags />

                @include('ancillaryCosts.form')
            </div>
        </form>
    </div>
</x-app-layout>
