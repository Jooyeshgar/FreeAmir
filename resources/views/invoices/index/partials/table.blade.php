@if ($invoices->count())
<div class="overflow-x-auto lg:overflow-x-hidden"
    x-data="{
        tooltipText: '',
        tooltipStyle: {},
        tooltipDirection: 'rtl',
        activeTrigger: null,
        hideTooltip() {
            this.tooltipText = '';
            this.activeTrigger = null;
        },
        showTooltip(event) {
            const trigger = event.target.closest('[data-tip]');
            if (!trigger || !trigger.dataset.tip.trim() || trigger === this.activeTrigger) return;
            if (!trigger.classList.contains('tooltip') &amp;&amp; !window.matchMedia('(min-width: 1024px)').matches) return;
            this.activeTrigger = trigger;
            this.tooltipText = trigger.dataset.tip;
            this.tooltipDirection = getComputedStyle(trigger).direction;
            this.$nextTick(() => {
                if (this.activeTrigger !== trigger) return;
                const rect = trigger.getBoundingClientRect();
                const tooltip = this.$refs.floatingTooltip;
                const gap = 8;
                const left = Math.max(gap, Math.min(rect.left + (rect.width - tooltip.offsetWidth) / 2, window.innerWidth - tooltip.offsetWidth - gap));
                const top = rect.top >= tooltip.offsetHeight + gap * 2 ? rect.top - tooltip.offsetHeight - gap : rect.bottom + gap;
                this.tooltipStyle = {
                    left: left + 'px',
                    top: Math.max(gap, Math.min(top, window.innerHeight - tooltip.offsetHeight - gap)) + 'px',
                };
            });
        },
        leaveTooltip(event) {
            if (this.activeTrigger &amp;&amp; !this.activeTrigger.contains(event.relatedTarget)) this.hideTooltip();
        },
    }"
    :class="'invoice-tooltips-ready [&_[data-tip]::before]:hidden [&_[data-tip]::after]:hidden'"
    @pointerover="showTooltip($event)" @pointerout="leaveTooltip($event)"
    @focusin="showTooltip($event)" @focusout="leaveTooltip($event)"
    @keydown.escape.window="hideTooltip()" @scroll.window.capture="hideTooltip()" @resize.window="hideTooltip()">
    <template x-teleport="body">
        <div x-ref="floatingTooltip" x-show="tooltipText" x-cloak role="tooltip"
            x-text="tooltipText" :style="tooltipStyle" :dir="tooltipDirection"
            class="fixed z-[100] w-max max-w-[min(20rem,calc(100vw-1rem))] rounded-field bg-neutral px-2 py-1 text-center text-sm leading-tight text-neutral-content whitespace-pre-line break-words pointer-events-none"></div>
    </template>
    <table class="table w-full">
        <thead>
            <tr>
                <th class="px-4 py-2">{{ __('Bookmark') }}</th>
                <th class="px-4 py-2">{{ __('Invoice Number') }}</th>
                <th class="px-4 py-2">{{ __('Customer') }}</th>
                <th class="px-4 py-2">{{ __('Document') }}</th>
                <th class="px-4 py-2">{{ __('Date') }}</th>
                <th class="px-4 py-2">{{ __('Price') }} ({{ config('amir.currency') ?? __('Rial') }})</th>
                <th class="px-4 py-2">{{ __('Status') }}</th>
                @if ($showMoadianColumn)
                    <th class="px-4 py-2">{{ __('Moadian Status') }}</th>
                @endif
                <th class="px-4 py-2">{{ __('Action') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoices as $invoice)
                @php
                    $isVoided = $isSellWorkflow && (bool) $invoice->voidInvoice;
                    $fiscalYearClosed = $invoice->fiscalYear?->isClosed() ?? false;
                    $fiscalYearClosedMessage = __('This fiscal year is already closed.');
                    $canApprove = ($isSellWorkflow ? false : $invoice->status->isPending())
                        || $invoice->status->isReadyToApprove()
                        || $invoice->status->isUnapproved()
                        || $invoice->status->isApprovedInactive();
                    $canUnapprove = $invoice->status->isApproved();
                    $isSettled = $invoice->status->isPartiallyPaid() || $invoice->status->isPaid();
                    $canChangeStatus = ($canApprove || $canUnapprove) && !$fiscalYearClosed;
                    $editDisabled = $fiscalYearClosed || $isVoidWorkflow || $invoice->status->isApprovedOrSettled() || $isVoided;
                    $deleteDisabled = $fiscalYearClosed || $invoice->status->isApprovedOrSettled() || $isVoided;
                    $settledTooltip = __('A paid or partially paid invoice cannot change its status until its payments (or their payment documents) are removed.');
                    $editTooltip = $fiscalYearClosed ? $fiscalYearClosedMessage : ($isVoidWorkflow ? __('Editing is not allowed for void invoices.') : ($isVoided ? __('Voided invoices cannot be edited') : ($isSettled ? $settledTooltip : __('Unapprove the invoice first to edit'))));
                    $deleteTooltip = $fiscalYearClosed ? $fiscalYearClosedMessage : ($isVoided ? __('Voided invoices cannot be deleted') : ($isSettled ? $settledTooltip : __('Unapprove the invoice first to delete')));
                    $moadianStatus = $showMoadianColumn ? \App\Enums\MoadianStatus::fromData($invoice->latestMoadianHistory?->data['status'] ?? null) : null;
                @endphp
                <tr class="{{ $isVoided ? 'text-gray-500' : '' }}">
                    <td class="px-4 py-2"><x-invoice-bookmark :invoice="$invoice" /></td>
                    <td class="px-4 py-2">
                        <div class="inline-flex items-center gap-1.5">
                            <a href="{{ route('invoices.show', $invoice) }}" class="link link-hover">{{ formatDocumentNumber($invoice->number) }}</a>
                            <x-extension-slot slot="invoice.index.row.badges" :context="['invoice' => $invoice]" />
                        </div>
                    </td>
                    <td class="px-4 py-2">
                        <a href="{{ route('customers.show', $invoice->customer) }}">{{ $invoice->customer->name ?? '' }}</a>
                        <br>
                        <span class="text-xs hidden lg:inline {{ $isVoided ? 'text-gray-400' : 'text-gray-500' }}">{{ $invoice->title ?? '' }}</span>
                    </td>
                    <td class="px-4 py-2">
                        @if ($invoice->document_id)
                            @can('documents.show')
                                <a href="{{ route('documents.show', $invoice->document_id) }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    {{ formatDocumentNumber($invoice->document->number) ?? '' }}
                                </a>
                            @else
                                <span class="text-gray-500">{{ formatDocumentNumber($invoice->document->number) ?? '' }}</span>
                            @endcan
                        @endif
                    </td>
                    <td class="px-4 py-2">{{ isset($invoice->date) ? formatDate($invoice->date) : '' }}</td>
                    <td class="px-4 py-2">{{ isset($invoice->amount) ? formatNumber($invoice->amount - $invoice->subtraction) : '' }}</td>
                    <td class="px-4 py-2">{{ $invoice->status?->label() ?? '' }}</td>
                    @if ($showMoadianColumn)
                        <td class="px-4 py-2">
                            @if ($invoice->latestMoadianHistory === null)
                                <span class="text-gray-400">{{ __('Not sent') }}</span>
                            @elseif ($moadianStatus !== null)
                                <span class="badge {{ $moadianStatus->color() }}">{{ $moadianStatus->label() }}</span>
                            @else
                                <span class="badge badge-warning">{{ __('UNKNOWN') }}</span>
                            @endif
                        </td>
                    @endif
                    <td class="px-4 py-2 whitespace-nowrap">
                        <div class="flex flex-nowrap items-center gap-2">
                        <a href="{{ route('invoices.show', $invoice) }}" target="_blank" rel="noopener" class="btn btn-sm btn-info">{{ __('Show') }}</a>

                        @can('invoices.approve')
                            @if ($isSellWorkflow && ($invoice->status->isPreInvoice() || $invoice->status->isRejected()))
                                @if ($fiscalYearClosed)
                                    <button class="btn btn-sm btn-success btn-disabled cursor-not-allowed" disabled title="{{ $fiscalYearClosedMessage }}">{{ __('Issue') }}</button>
                                @else
                                <form action="{{ route('invoices.change-status', [$invoice, 'ready_to_approve']) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-success">{{ __('Issue') }}</button>
                                </form>
                                @endif
                            @endif
                            @if ($isSellWorkflow && $invoice->status->isPreInvoice())
                                @if ($fiscalYearClosed)
                                    <button class="btn btn-sm btn-error btn-disabled cursor-not-allowed" disabled title="{{ $fiscalYearClosedMessage }}">{{ __('Reject') }}</button>
                                @else
                                <form action="{{ route('invoices.change-status', [$invoice, 'rejected']) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-error">{{ __('Reject') }}</button>
                                </form>
                                @endif
                            @endif
                            @if ($canChangeStatus)
                                @if ($invoice->changeStatusValidation->hasErrors())
                                    <a data-tip="{{ $invoice->changeStatusValidation->toText() }}" href="{{ route('invoices.conflicts', $invoice) }}"
                                        class="btn btn-sm btn-accent inline-flex tooltip {{ $loop->first ? 'tooltip-bottom' : '' }}">{{ __('Fix Conflict') }}</a>
                                @elseif ($isVoided)
                                    <span class="lg:tooltip {{ $loop->first ? 'lg:tooltip-bottom' : '' }}" data-tip="{{ __('To change the status, first unapprove the voiding invoice.') }}">
                                        <button class="btn btn-sm btn-error btn-disabled cursor-not-allowed">{{ __('Unapprove') }}</button>
                                    </span>
                                @else
                                    <form action="{{ route('invoices.change-status', [$invoice, $canUnapprove ? 'unapproved' : 'approved']) }}{{ ($invoice->changeStatusValidation->hasWarning() || $isSettled) ? '?confirm=1' : '' }}"
                                        method="POST" class="inline-block {{ ($invoice->changeStatusValidation->hasWarning() || $isSettled) ? 'change-status-form' : '' }}"
                                        data-confirm-message="{{ $isSettled ? __('Deactivating this paid or partially paid invoice will remove its payment accounting documents while keeping the payment records. Are you sure?') : __('This invoice has warnings for change its status, are you sure to change status?') }}">
                                        @csrf
                                        <button type="submit" x-data="{}" data-tip="{{ $invoice->changeStatusValidation->toText() }}"
                                            class="btn btn-sm inline-flex tooltip {{ $loop->first ? 'tooltip-bottom' : '' }} {{ $canUnapprove ? 'btn-warning' : 'btn-success' }} {{ $canApprove && $invoice->changeStatusValidation->hasWarning() ? 'btn-outline' : '' }}">
                                            {{ $canUnapprove ? __('Unapprove') : __('Approve') }}
                                        </button>
                                    </form>
                                @endif
                            @elseif ($fiscalYearClosed)
                                <span class="lg:tooltip {{ $loop->first ? 'lg:tooltip-bottom' : '' }}" data-tip="{{ $fiscalYearClosedMessage }}">
                                    <button class="btn btn-sm btn-warning btn-disabled cursor-not-allowed" disabled>{{ __('Change status') }}</button>
                                </span>
                            @elseif ($isSettled)
                                <span class="lg:tooltip {{ $loop->first ? 'lg:tooltip-bottom' : '' }}" data-tip="{{ $settledTooltip }}">
                                    <button class="btn btn-sm btn-warning btn-disabled cursor-not-allowed">{{ __('Unapprove') }}</button>
                                </span>
                            @endif
                        @endcan

                        @if (!$editDisabled)
                            <a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-sm btn-info">{{ __('Edit') }}</a>
                        @else
                            <span class="lg:tooltip lg:tooltip-right" data-tip="{{ $editTooltip }}">
                                <button class="btn btn-sm btn-error btn-disabled cursor-not-allowed">{{ __('Edit') }}</button>
                            </span>
                        @endif

                        @if (!$deleteDisabled)
                            <form action="{{ route('invoices.destroy', $invoice) }}{{ $isSettled ? '?confirm=1' : '' }}" method="POST" class="{{ $isSettled ? 'delete-settled-form' : '' }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-error">{{ __('Delete') }}</button>
                            </form>
                        @else
                            <span class="lg:tooltip lg:tooltip-right" data-tip="{{ $deleteTooltip }}">
                                <button class="btn btn-sm btn-error btn-disabled cursor-not-allowed">{{ __('Delete') }}</button>
                            </span>
                        @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@else
    @php
        $hasInvoiceFilters = collect(request()->except(['page', 'invoice_type', 'service_buy']))
            ->filter(fn ($v) => $v !== null && $v !== '' && $v !== 'all')
            ->isNotEmpty();

        $resetParams = ['invoice_type' => $invoiceType];
        if (in_array($invoiceType, ['buy', 'return_buy']) && request()->has('service_buy')) {
            $resetParams['service_buy'] = request('service_buy');
        }

        $isFiscalYearClosed = \App\Models\FiscalYear::find(getActiveFiscalYear())?->isClosed() ?? false;

        $invoicePrerequisites = [];
        if (! $hasInvoiceFilters && in_array($invoiceType, ['sell', 'buy'])) {
            $returnTo = request()->getRequestUri();
            $user = auth()->user();

            $customerActions = [];
            if ($user?->can('customers.create') || $user?->can('customers.*')) {
                $customerActions[] = [
                    'label' => __('Add customer'),
                    'url' => route('customers.create', ['return_to' => $returnTo]),
                ];
            }

            $catalogActions = [];
            $isServiceOnlyBuy = $invoiceType === 'buy' && request()->boolean('service_buy');
            if (! $isServiceOnlyBuy && ($user?->can('products.create') || $user?->can('products.*'))) {
                $catalogActions[] = [
                    'label' => __('Add product'),
                    'url' => route('products.create', ['return_to' => $returnTo]),
                ];
            }
            if ($user?->can('services.create') || $user?->can('services.*')) {
                $catalogActions[] = [
                    'label' => __('Add service'),
                    'url' => route('services.create', ['return_to' => $returnTo]),
                ];
            }

            $invoicePrerequisites = [
                [
                    'key' => 'customer',
                    'title' => __('First customer'),
                    'done' => \App\Models\Customer::query()->exists(),
                    'actions' => $customerActions,
                ],
                [
                    'key' => 'catalog',
                    'title' => $isServiceOnlyBuy ? __('First service') : __('Product or service'),
                    'done' => $isServiceOnlyBuy
                        ? \App\Models\Service::query()->exists()
                        : (\App\Models\Product::query()->exists() || \App\Models\Service::query()->exists()),
                    'actions' => $catalogActions,
                ],
            ];
        }
    @endphp
    <div class="mt-3">
        <x-empty-state
            :compact="true"
            :filtered="$hasInvoiceFilters"
            :filtered-title="__('No invoices found.')"
            :reset-url="route('invoices.index', $resetParams)"
            :title="__('No invoices recorded yet')"
            :description="__('Approving an invoice automatically creates its accounting document and updates inventory.')"
            :prerequisites="$invoicePrerequisites"
            :prerequisites-title="__('Prepare these prerequisites first (after saving, you will return right here):')"
            :tip="in_array($invoiceType, ['sell', 'buy']) ? __('Draft invoices can be edited or deleted anytime before final approval.') : null"
            :fiscal-year-closed="$isFiscalYearClosed"
        >
            <x-slot:icon>
                <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2Z" />
                </svg>
            </x-slot:icon>
        </x-empty-state>
    </div>
@endif

@if (request('status') !== null)
    @php
        $backParams = ['invoice_type' => $invoiceType];
        if (request()->boolean('bookmarked')) {
            $backParams['bookmarked'] = 1;
        }
        if (in_array($invoiceType, ['buy', 'return_buy'])) {
            $backParams['service_buy'] = request('service_buy');
        }
    @endphp
    <div class="px-4 py-2 text-left">
        <a class="btn btn-primary" href="{{ route('invoices.index', $backParams) }}">{{ __('Back') }}</a>
    </div>
@endif

{{ $invoices->withQueryString()->links() }}
