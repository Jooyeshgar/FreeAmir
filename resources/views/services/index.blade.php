<x-app-layout :title="__('Services')">
    <x-show-message-bags />

    {{-- Page Header --}}
    <div class="flex flex-wrap items-center justify-between gap-4 px-1 pb-5">
        <div class="min-w-48">
            <div class="flex flex-wrap items-center">
                <h1 class="text-xl font-bold text-base-content">{{ __('Services') }}</h1>
                <x-user-guide-link source="warehouse/products/products.md" />
            </div>
            <p class="text-sm text-base-content/50 mt-0.5">{{ __('Manage your services and their accounts') }}</p>
        </div>

        <div class="flex flex-wrap items-center justify-start gap-2">
            <x-closed-year-action-link :fiscal-year-closed="$fiscalYearClosed" href="{{ route('services.create') }}" class="btn btn-primary btn-sm">{{ __('Create service') }}</x-closed-year-action-link>
            <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('service-csv-modal').showModal()">{{ __('Receive Report') }}</button>
            <x-closed-year-action-link :fiscal-year-closed="$fiscalYearClosed" href="{{ route('services.import') }}" class="btn btn-outline btn-sm">{{ __('Import CSV') }}</x-closed-year-action-link>
        </div>
    </div>

    <dialog id="service-csv-modal" class="modal">
        <div class="modal-box max-w-2xl">
            <h3 class="text-lg font-bold">{{ __('Receive Report') }}</h3>

            <form id="service-csv-export-form" action="{{ route('services.export') }}" method="GET">
                @csrf
                <input type="hidden" name="export" value="services_csv">
                <x-input name="cols_submitted" value="1" hidden />

                <div class="mt-2 text-info">{{ __('The name column is always exported.') }}</div>

                <div class="mt-4">
                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-sm font-semibold">{{ __('Optional columns') }}</span>
                        <div class="text-xs [&_.fieldset]:m-0 [&_.label]:p-0 [&_.checkbox]:checkbox-sm">
                            <x-checkbox name="" id="service-csv-cols-toggle" :title="__('Select All')" :checked="true"
                                onchange="document.querySelectorAll('#service-csv-modal input[name=&quot;columns[]&quot;]').forEach(cb => cb.checked = this.checked)" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-1 sm:grid-cols-2">
                        @foreach ($csvColumns as $key => $label)
                            @continue($key === 'name')
                            <div class="[&_.fieldset]:m-0 [&_.label]:p-0 [&_.checkbox]:checkbox-sm">
                                <x-checkbox name="columns[]" :id="'service-csv-column-'.$key" :value="$key" :title="$label" :checked="true"
                                    onchange="document.getElementById('service-csv-cols-toggle').checked = document.querySelectorAll('#service-csv-modal input[name=&quot;columns[]&quot;]:checked').length === document.querySelectorAll('#service-csv-modal input[name=&quot;columns[]&quot;]').length" />
                            </div>
                        @endforeach
                    </div>
                </div>

                <label class="fieldset w-full mt-4">
                    <span class="label">{{ __('Recipient email') }}</span>
                    <input type="email" name="email" value="{{ old('email', auth()->user()->email) }}" required maxlength="255" class="input input-bordered w-full" dir="ltr" autocomplete="email">
                </label>
                <p class="mt-2 text-sm text-base-content/70">{{ __('You can replace your email address to send this report to someone else. The email will identify you as the requester.') }}</p>

                <div class="modal-action">
                    <button type="button" class="btn btn-sm md:btn-sm lg:btn-md btn-ghost" onclick="document.getElementById('service-csv-modal').close()">{{ __('Cancel') }}</button>
                    <button type="submit" name="delivery" value="download" formaction="{{ route('report') }}" formmethod="POST" formnovalidate class="btn btn-sm md:btn-sm lg:btn-md btn-primary">{{ __('Download') }}</button>
                    <button type="submit" name="delivery" value="email" formaction="{{ route('report') }}" formmethod="POST" class="btn btn-sm md:btn-sm lg:btn-md btn-secondary">{{ __('Send to email') }}</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button aria-label="close"></button>
        </form>
    </dialog>
    {{-- Service List --}}
    <div class="card bg-base-100 shadow-sm border border-base-200 mx-1 mb-6">
        <div class="card-body p-0">
            {{-- Card Header: title + filters --}}
            <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-base-200">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-base font-bold text-base-content">{{ __('Services') }}</h2>
                    <span class="badge badge-ghost">
                        {{ localizeNumber($services->total()) }} {{ __('records') }}
                    </span>
                </div>

                <form action="{{ route('services.index') }}" method="GET" class="flex flex-wrap items-center gap-2">
                    <div class="relative w-44 max-w-full [&_.input]:input-sm">
                        <x-input type="text" name="name" value="{{ request('name') }}" placeholder="{{ __('Service Name') }}" />
                    </div>

                    <div class="relative w-44 max-w-full [&_.input]:input-sm">
                        <x-input type="text" name="group_name" value="{{ request('group_name') }}" placeholder="{{ __('Service Group Name') }}" />
                    </div>

                    <button type="submit" class="btn btn-sm btn-neutral">{{ __('Search') }}</button>
                </form>
            </div>

            <div class="p-4 sm:p-5 overflow-auto">
            @if ($services->count())
            <table class="table w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-2">{{ __('Service Code') }}</th>
                        <th class="px-4 py-2">{{ __('Name') }}</th>
                        <th class="px-4 py-2">{{ __('Sell price') }} ({{ config('amir.currency') ?? __('Rial') }})
                        </th>
                        <th class="px-4 py-2">{{ __('VAT') }} ({{ config('amir.currency') ?? __('Rial') }})
                        </th>
                        <th class="px-4 py-2">{{ __('Service group') }}</th>
                        <th class="px-4 py-2">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>

                    @foreach ($services as $service)
                        <tr>
                            <td class="px-4 py-2">{{ localizeNumber($service->code) }}</td>
                            <td class="px-4 py-2">
                                <a href="{{ route('services.show', $service) }}" class="text-primary">
                                    {{ $service->name }}</a>
                            </td>
                            <td class="px-4 py-2">{{ formatNumber($service->selling_price) }}</td>
                            <td class="px-4 py-2">{{ formatNumber($service->vat) }}%</td>
                            <td class="px-4 py-2">
                                <a
                                    href="{{ route('service-groups.show', $service->serviceGroup) }}">{{ $service->serviceGroup ? $service->serviceGroup->name : '' }}</a>
                            </td>
                            <td class="px-4 py-2">
                                <div class="inline-flex gap-2">
                                    <x-closed-year-action-link :fiscal-year-closed="$fiscalYearClosed" href="{{ route('services.edit', $service) }}" class="btn btn-sm btn-info">{{ __('Edit') }}</x-closed-year-action-link>
                                    @if ($service->invoiceItems()->exists())
                                        <button class="btn btn-sm btn-info btn-disabled cursor-not-allowed" disabled title="{{ $fiscalYearClosed ? __('This fiscal year is already closed.') : __('Cannot delete service that is used in invoice items') }}">{{ __('Delete') }}</button>
                                    @else
                                        <form action="{{ route('services.destroy', $service) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-error" @disabled($fiscalYearClosed) title="{{ $fiscalYearClosed ? __('This fiscal year is already closed.') : '' }}">{{ __('Delete') }}</button>
                                        </form>
                                    @endif
                                </div>
                                
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @else
                @php
                    $hasServiceFilters = request()->filled('name') || request()->filled('group_name');
                @endphp
                <x-empty-state
                    :filtered="$hasServiceFilters"
                    :filtered-title="__('No services found.')"
                    :reset-url="route('services.index')"
                    :title="__('No services defined yet')"
                    :description="__('Define the services you sell or purchase so you can select them directly on invoices without needing warehouse stock.')"
                >
                    <x-slot:icon>
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z" />
                        </svg>
                    </x-slot:icon>
                </x-empty-state>
            @endif
            </div>

            {{-- Pagination --}}
            @if ($services->hasPages())
                <div class="px-5 py-4 border-t border-base-200">
                    {!! $services->withQueryString()->links() !!}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
