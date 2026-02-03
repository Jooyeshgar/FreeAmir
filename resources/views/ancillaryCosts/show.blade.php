@php
    /** @var \App\Models\AncillaryCost $ancillaryCost */
    /** @var array{allowed: bool, reason: string|null} $editDeleteStatus */
    /** @var array{allowed: bool, reason?: string|null} $changeStatusValidation */
@endphp

<x-app-layout :title="__('Ancillary Cost') . ' #' . formatDocumentNumber($ancillaryCost->number ?? $ancillaryCost->id)">

    <div class="card bg-base-100 shadow-xl">
        <div class="card-header bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-gray-800 dark:to-gray-700 px-6 py-4 rounded-t-2xl border-b-2 border-primary/20">
            <div>
                <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
                    {{ __('Ancillary Cost') }} #{{ formatDocumentNumber($ancillaryCost->number ?? $ancillaryCost->id) }}
                    @if ($ancillaryCost->type)
                        - {{ $ancillaryCost->type->label() }}
                    @endif
                </h2>
                <p class="mt-1 float-end">
                    {{ __('Issued on :date', ['date' => $ancillaryCost->date ? formatDate($ancillaryCost->date) : __('Unknown')]) }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2 mt-2">
                <span class="badge badge-lg badge-primary gap-2">
                    {{ $ancillaryCost->type?->label() ?? __('Ancillary Cost') }}
                </span>
                <span class="badge badge-lg {{ $ancillaryCost->status?->isApproved() ? 'badge-primary' : 'badge-warning' }} gap-2">
                    {{ $ancillaryCost->status?->label() ?? '—' }}
                </span>
            </div>
        </div>

        <div class="card-body space-y-8">
            <x-show-message-bags />

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="stats shadow bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200/60">
                    <div class="stat">
                        <div class="stat-title text-blue-500">{{ __('Amount') }} ({{ config('amir.currency') ?? __('Rial') }})</div>
                        <div class="stat-value text-blue-600 text-3xl">{{ formatNumber((float) ($ancillaryCost->amount ?? 0) - ($ancillaryCost->vat ?? 0)) }}</div>
                    </div>
                </div>

                <div class="stats shadow bg-gradient-to-br from-emerald-50 to-emerald-100 border border-emerald-200/60">
                    <div class="stat">
                        <div class="stat-title text-emerald-500">{{ __('VAT') }} ({{ config('amir.currency') ?? __('Rial') }})</div>
                        <div class="stat-value text-emerald-600 text-3xl">{{ formatNumber((float) ($ancillaryCost->vat ?? 0)) }}</div>
                    </div>
                </div>

                <div class="stats shadow bg-gradient-to-br from-indigo-50 to-indigo-100 border border-indigo-200/60">
                    <div class="stat">
                        <div class="stat-title text-indigo-500">{{ __('Total') }} ({{ config('amir.currency') ?? __('Rial') }})</div>
                        <div class="stat-value text-indigo-600 text-3xl">{{ formatNumber((float) (($ancillaryCost->amount ?? 0))) }}</div>
                    </div>
                </div>

                <div class="stats shadow bg-gradient-to-br from-amber-50 to-amber-100 border border-amber-200/60">
                    <div class="stat">
                        <div class="stat-title text-amber-500">{{ __('Invoice') }}</div>
                        <div class="stat-value text-amber-600 text-2xl">
                            <a class="link link-hover link-primary" href="{{ route('invoices.show', $ancillaryCost->invoice_id) }}">
                                {{ formatDocumentNumber($ancillaryCost->invoice?->number ?? $ancillaryCost->invoice_id) }}
                            </a>
                        </div>
                    </div>
                </div>

                @can('ancillary-costs.approve')
                    @if (!empty($changeStatusValidation) && ($changeStatusValidation['allowed'] ?? false))
                        <div class="border col-span-1 md:col-span-2 lg:col-span-4">
                            <div class="stat">
                                <div class="stat-title">{{ __('Status') }}</div>
                                <div class="stat-value">
                                    <a href="{{ route('ancillary-costs.change-status', [$ancillaryCost, $ancillaryCost->status?->isApproved() ? 'unapprove' : 'approve']) }}"
                                        class="btn {{ $ancillaryCost->status?->isApproved() ? 'btn-warning' : 'btn-success' }}">
                                        {{ __($ancillaryCost->status?->isApproved() ? 'Unapprove' : 'Approve') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    @elseif (!empty($changeStatusValidation) && !($changeStatusValidation['allowed'] ?? true))
                        <div class="border col-span-1 md:col-span-2 lg:col-span-4">
                            <div class="stat">
                                <div class="stat-title">{{ __('Status') }}</div>
                                <div class="stat-value">
                                    <span class="tooltip" data-tip="{{ $changeStatusValidation['reason'] ?? '' }}">
                                        <button class="btn {{ $ancillaryCost->status?->isApproved() ? 'btn-warning' : 'btn-success' }} btn-disabled cursor-not-allowed">
                                            {{ __($ancillaryCost->status?->isApproved() ? 'Unapprove' : 'Approve') }}
                                        </button>
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endif
                @endcan
            </div>

            <div>
                <div class="divider text-lg font-semibold">{{ __('Customer Details') }}</div>
                @if ($ancillaryCost->customer)
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="card bg-base-200 shadow">
                            <div class="card-body p-4">
                                <h3 class="card-title text-xs uppercase tracking-wide text-gray-500">{{ __('Customer') }}</h3>
                                <p class="text-lg font-semibold text-gray-800">{{ $ancillaryCost->customer->name }}</p>
                            </div>
                        </div>
                        <div class="card bg-base-200 shadow">
                            <div class="card-body p-4">
                                <h3 class="card-title text-xs uppercase tracking-wide text-gray-500">{{ __('Phone') }}</h3>
                                <p class="text-lg font-semibold text-gray-800">
                                    {{ $ancillaryCost->customer->phone ? convertToFarsi($ancillaryCost->customer->phone) : '—' }}
                                </p>
                            </div>
                        </div>
                        <div class="card bg-base-200 shadow">
                            <div class="card-body p-4">
                                <h3 class="card-title text-xs uppercase tracking-wide text-gray-500">{{ __('Economic code') }}</h3>
                                <p class="text-lg font-semibold text-gray-800">
                                    {{ $ancillaryCost->customer->ecnmcs_code ? convertToFarsi($ancillaryCost->customer->ecnmcs_code) : '—' }}
                                </p>
                            </div>
                        </div>
                        <div class="card bg-base-200 shadow">
                            <div class="card-body p-4">
                                <h3 class="card-title text-xs uppercase tracking-wide text-gray-500">{{ __('Postal code') }}</h3>
                                <p class="text-lg font-semibold text-gray-800">
                                    {{ $ancillaryCost->customer->postal_code ? convertToFarsi($ancillaryCost->customer->postal_code) : '—' }}
                                </p>
                            </div>
                        </div>
                        <div class="card bg-base-200 shadow md:col-span-2 lg:col-span-4">
                            <div class="card-body p-4">
                                <h3 class="card-title text-xs uppercase tracking-wide text-gray-500">{{ __('Address') }}</h3>
                                <p class="text-sm font-medium text-gray-700 leading-relaxed">
                                    {{ $ancillaryCost->customer->address ?: '—' }}
                                </p>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="alert bg-emerald-50 border border-emerald-200 text-emerald-700">
                        <span>{{ __('No customer is attached to this ancillary cost.') }}</span>
                    </div>
                @endif
            </div>

            <div>
                <div class="divider text-lg font-semibold">{{ __('Document') }}</div>
                @if ($ancillaryCost->document_id)
                    <div class="alert bg-base-200 shadow-sm">
                        <div class="flex flex-wrap gap-2 items-center">
                            <span>{{ __('Doc Number') }}:</span>
                            @can('documents.show')
                                <a class="link link-hover link-primary" href="{{ route('documents.show', $ancillaryCost->document_id) }}">{{ formatDocumentNumber($ancillaryCost->document?->number ?? $ancillaryCost->document_id) }}</a>
                            @else
                                <span class="text-gray-500">{{ formatDocumentNumber($ancillaryCost->document?->number ?? $ancillaryCost->document_id) }}</span>
                            @endcan
                        </div>
                    </div>
                @else
                    <div class="alert bg-base-200 shadow-sm">
                        <div>
                            <span>{{ __('No document is attached to this ancillary cost.') }}</span>
                        </div>
                    </div>
                @endif
            </div>

            <div>
                <div class="divider text-lg font-semibold">{{ __('Items') }}</div>

                @if ($ancillaryCost->items->isNotEmpty())
                    <div class="overflow-x-auto shadow-lg rounded-lg">
                        <table class="table table-zebra w-full">
                            <thead class="bg-base-300">
                                <tr>
                                    <th class="px-4 py-3">#</th>
                                    <th class="px-4 py-3">{{ __('Product') }}</th>
                                    <th class="px-4 py-3 text-right">{{ __('Amount') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($ancillaryCost->items as $index => $item)
                                    <tr class="hover">
                                        <td class="px-4 py-3">{{ convertToFarsi($index + 1) }}</td>
                                        <td class="px-4 py-3">
                                            @if ($item->product)
                                                <a href="{{ route('products.show', $item->product) }}" class="link link-hover link-primary">
                                                    {{ $item->product->name }}
                                                </a>
                                            @else
                                                <span class="text-gray-500">{{ __('Removed product') }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right">{{ formatNumber((float) ($item->amount ?? 0)) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="alert bg-base-200 shadow-sm">
                        <div>
                            <span>{{ __('There are no items on this ancillary cost yet.') }}</span>
                        </div>
                    </div>
                @endif
            </div>

            <div class="card-actions justify-between mt-4">
                <a href="{{ isset($invoice) && $invoice ? route('invoices.show', $invoice) : route('ancillary-costs.index') }}" class="btn btn-ghost gap-2">
                    {{ __('Back') }}
                </a>

                <div class="flex flex-wrap gap-2">
                    @if (!empty($editDeleteStatus) && ($editDeleteStatus['allowed'] ?? false) && !$ancillaryCost->status?->isApproved())
                        @can('ancillary-costs.edit')
                            <a href="{{ route('invoices.ancillary-costs.edit', [$invoice ?? $ancillaryCost->invoice_id, $ancillaryCost]) }}" class="btn btn-primary gap-2">
                                {{ __('Edit') }}
                            </a>
                        @endcan
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
