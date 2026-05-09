<x-app-layout :title="__('Edit Ancillary Cost')">
    <div>
        <form action="{{ isset($invoice) && $invoice ? route('invoices.ancillary-costs.update', [$invoice, $ancillaryCost]) : route('ancillary-costs.update', $ancillaryCost) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                <div class="card-title">{{ __('Edit Ancillary Cost') }}</div>
                <x-show-message-bags />

                @include('ancillaryCosts.form')
            </div>
        </form>
    </div>
</x-app-layout>