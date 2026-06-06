<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    @php
        $statusFilter = request('status');
        $baseQuery = request()->except('page');
        $statusTypes = [
            \App\Enums\InvoiceStatus::PENDING->value => 'info',
            \App\Enums\InvoiceStatus::APPROVED->value => 'success',
            \App\Enums\InvoiceStatus::UNAPPROVED->value => 'warning',
            \App\Enums\InvoiceStatus::PRE_INVOICE->value => 'info',
            \App\Enums\InvoiceStatus::APPROVED_INACTIVE->value => 'error',
            \App\Enums\InvoiceStatus::REJECTED->value => 'error',
            \App\Enums\InvoiceStatus::READY_TO_APPROVE->value => 'info',
        ];
    @endphp
    @foreach (\App\Enums\InvoiceStatus::cases() as $status)
        @if ($isSellWorkflow ? $status->isPending() : ($status->isReadyToApprove() || $status->isPreInvoice() || $status->isRejected()))
            @continue
        @endif
        @php
            $value = $status->value;
            $count = $statusCounts->get($value, 0);
            $isActive = $statusFilter == $value;
            $url = route('invoices.index', array_merge($baseQuery, ['status' => $value]));
            $type = $statusTypes[$value] ?? 'info';
        @endphp
        <a href="{{ $url }}" class="block transition-transform hover:scale-105 {{ $isActive ? 'ring-2 ring-primary rounded-xl' : '' }}">
            <x-stat-card :title="$status->label()" :value="convertToFarsi($count)" :type="$type" />
        </a>
    @endforeach
    <x-stat-card :title="$quantityTitle" :value="$quantityValue" />
    <x-stat-card :title="__('Invoices Amount')" :value="formatNumber($invoices->totalAmount)" />
</div>