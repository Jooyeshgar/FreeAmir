<x-app-layout :title="__('Service Groups')">
    <x-show-message-bags />

    {{-- Page Header --}}
    <div class="flex flex-wrap items-center justify-between gap-4 px-1 pb-5">
        <div class="min-w-48">
            <h1 class="text-xl font-bold text-base-content">{{ __('Service Groups') }}</h1>
            <p class="text-sm text-base-content/50 mt-0.5">{{ __('Manage your service groups and their accounts') }}</p>
        </div>

        <div class="flex flex-wrap items-center justify-start gap-2">
            <a href="{{ route('service-groups.create') }}" class="btn btn-primary btn-sm gap-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                {{ __('Create service group') }}
            </a>
        </div>
    </div>

    {{-- Service Group List --}}
    <div class="card bg-base-100 shadow-sm border border-base-200 mx-1 mb-6">
        <div class="card-body p-0">
            {{-- Card Header --}}
            <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-base-200">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-base font-bold text-base-content">{{ __('Service Groups') }}</h2>
                    <span class="badge badge-ghost">
                        {{ convertToFarsi($serviceGroups->total()) }} {{ __('records') }}
                    </span>
                </div>
            </div>

            <div class="p-4 sm:p-5">
            <table class="table w-full overflow-auto">
                <thead>
                    <tr>
                        <th class="px-4 py-2">{{ __('SSTID') }}</th>
                        <th class="px-4 py-2">{{ __('Name') }}</th>
                        <th class="px-4 py-2">{{ __('VAT') }}</th>
                        <th class="px-4 py-2">{{ __('Services') }}</th>
                        <th class="px-4 py-2">{{ __('Subject') }}</th>
                        <th class="px-4 py-2">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>

                    @foreach ($serviceGroups as $serviceGroup)
                        <tr>
                            <td class="px-4 py-2">{{ $serviceGroup->sstid }}</td>
                            <td class="px-4 py-2">
                                <a href="{{ route('services.index', ['group_name' => $serviceGroup->name]) }}"
                                    class="text-blue-600 hover:underline">
                                    {{ $serviceGroup->name }}
                                </a>
                            </td>
                            <td class="px-4 py-2">{{ formatNumber($serviceGroup->vat) }}%</td>
                            <td class="px-4 py-2">{{ formatNumber($serviceGroup->services->count()) }}</td>
                            <td class="px-4 py-2"><a
                                    href="{{ route('transactions.index', ['subject_id' => $serviceGroup->subject]) }}">{{ $serviceGroup->subject?->name }}</a>
                            </td>
                            <td class="px-4 py-2">
                                <a href="{{ route('service-groups.show', $serviceGroup) }}"
                                    class="btn btn-sm btn-info">{{ __('View') }}</a>
                                <a href="{{ route('service-groups.edit', $serviceGroup) }}"
                                    class="btn btn-sm btn-info">{{ __('Edit') }}</a>
                                <form action="{{ route('service-groups.destroy', $serviceGroup) }}" method="POST"
                                    class="inline-block">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-error">{{ __('Delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>

            {{-- Pagination --}}
            @if ($serviceGroups->hasPages())
                <div class="px-5 py-4 border-t border-base-200">
                    {!! $serviceGroups->links() !!}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
