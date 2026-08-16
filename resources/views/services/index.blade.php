<x-app-layout :title="__('Services')">
    <x-show-message-bags />

    {{-- Page Header --}}
    <div class="flex flex-wrap items-center justify-between gap-4 px-1 pb-5">
        <div class="min-w-48">
            <h1 class="text-xl font-bold text-base-content">{{ __('Services') }}</h1>
            <p class="text-sm text-base-content/50 mt-0.5">{{ __('Manage your services and their accounts') }}</p>
        </div>

        <div class="flex flex-wrap items-center justify-start gap-2">
            <a href="{{ route('services.create') }}" class="btn btn-primary btn-sm">{{ __('Create service') }}</a>
            <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('service-csv-modal').showModal()">{{ __('Receive Report') }}</button>
            <a href="{{ route('services.import') }}" class="btn btn-outline btn-sm">{{ __('Import CSV') }}</a>
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
                    <button type="button" class="btn btn-ghost" onclick="document.getElementById('service-csv-modal').close()">{{ __('Cancel') }}</button>
                    <button type="submit" name="delivery" value="download" formaction="{{ route('send-to-email') }}" formmethod="POST" formnovalidate class="btn btn-primary">{{ __('Download') }}</button>
                    <button type="submit" name="delivery" value="email" formaction="{{ route('send-to-email') }}" formmethod="POST" class="btn btn-secondary">{{ __('Send to email') }}</button>
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

            <div class="p-4 sm:p-5">
            <table class="table w-full overflow-auto">
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
                                <a href="{{ route('services.edit', $service) }}"
                                    class="btn btn-sm btn-info">{{ __('Edit') }}</a>
                                @if ($service->invoiceItems()->exists())
                                    <span class="tooltip"
                                        data-tip="{{ __('Cannot delete service that is used in invoice items') }}">
                                        <button class="btn btn-sm btn-info btn-disabled cursor-not-allowed" disabled
                                            title="{{ __('Cannot delete service that is used in invoice items') }}">{{ __('Delete') }}</button>
                                    </span>
                                @else
                                    <form action="{{ route('services.destroy', $service) }}" method="POST"
                                        class="inline-block">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="btn btn-sm btn-error">{{ __('Delete') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
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
