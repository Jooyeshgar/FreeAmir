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
            <a href="{{ route('services.export') }}" class="btn btn-outline btn-sm">{{ __('Export CSV') }}</a>
            <a href="{{ route('services.import') }}" class="btn btn-outline btn-sm">{{ __('Import CSV') }}</a>
        </div>
    </div>

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
