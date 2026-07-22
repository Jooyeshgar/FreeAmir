<x-app-layout :title="__('Audit Trail')">
    <style>
        #audit-log-table > section > header > div:last-child > form.join {
            gap: 0.75rem;
        }

        body > jdp-container,
        body > jdp-overlay {
            z-index: 1100 !important;
        }
    </style>

    <div x-data="{
        filtersOpen: false,
        closeFilters() {
            this.filtersOpen = false;
            window.jalaliDatepicker?.hide();
        }
    }">
    <div id="audit-log-table">
        <x-data-table :title="__('System-wide audit trail')" :description="__('Immutable operational context for authenticated model changes. Sensitive attributes are redacted.')"
            :rows="$auditLogs" :columns="[
                ['key' => 'actor.name', 'label' => 'Actor'],
                ['key' => 'company.name', 'label' => 'Company'],
                ['key' => 'method', 'label' => 'Method', 'format' => 'badge', 'sortable' => true],
                ['key' => 'action', 'label' => 'Action', 'sortable' => true, 'class' => 'font-mono text-xs'],
                ['key' => 'ip_address', 'label' => 'IP address', 'class' => 'font-mono text-xs'],
                ['key' => 'created_at', 'label' => 'Occurred at', 'format' => 'datetime', 'sortable' => true],
            ]"
            :actions="[['label' => 'Inspect', 'route' => 'admin.audit-logs.show']]"
            :search-placeholder="__('Search action, URL, or IP…')">
            <x-slot:filters>
                <button type="button" class="btn btn-sm btn-outline" x-on:click="filtersOpen = true">
                    {{ __('Filters') }}
                    @if (collect(request()->only(['user_id', 'company_id', 'method', 'from', 'to']))->filter()->isNotEmpty())
                        <span class="badge badge-primary badge-xs"></span>
                    @endif
                </button>
            </x-slot:filters>
        </x-data-table>
    </div>

    <div id="audit-filters" class="modal" x-cloak x-show="filtersOpen" x-bind:class="{ 'modal-open': filtersOpen }" x-on:keydown.escape.window="closeFilters()"
        role="dialog" aria-modal="true" aria-labelledby="audit-filters-title">
        <div class="modal-box" x-on:click.stop>
            <button type="button" class="btn btn-sm btn-circle btn-ghost absolute end-2 top-2" x-on:click="closeFilters()" aria-label="{{ __('Close') }}">✕</button>
            <h2 id="audit-filters-title" class="text-lg font-bold">{{ __('Filter audit events') }}</h2>
            <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="mt-5 grid gap-4 sm:grid-cols-2">
                <x-input name="search" id="search" :title="__('Search')" :value="request('search')" :placeholder="__('Action, URL, or IP address')" class="sm:col-span-2" />
                <x-select name="user_id" id="user_id" :title="__('Actor')" :selected="request('user_id')" :options="['' => __('All users')] + $users->pluck('name', 'id')->all()" />
                <x-select name="company_id" id="company_id" :title="__('Company')" :selected="request('company_id')" :options="['' => __('All companies')] + $companies->pluck('name', 'id')->all()" />
                <x-select name="method" id="method" :title="__('Method')" :selected="request('method')" 
                    :options="['' => __('All methods'),
                        'POST' => 'POST',
                        'PUT' => 'PUT',
                        'PATCH' => 'PATCH',
                        'DELETE' => 'DELETE',
                    ]" />
                <div></div>
                <x-date-picker name="from" id="from" :title="__('From')" :value="request('from')" />
                <x-date-picker name="to" id="to" :title="__('To')" :value="request('to')" />
                <div class="modal-action sm:col-span-2">
                    <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-ghost">{{ __('Clear') }}</a>
                    <button class="btn btn-primary" type="submit">{{ __('Apply filters') }}</button>
                </div>
            </form>
        </div>
        <button type="button" class="modal-backdrop" x-on:click="closeFilters()">{{ __('Close') }}</button>
    </div>
    </div>
</x-app-layout>
