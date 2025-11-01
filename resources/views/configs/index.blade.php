<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Configs') }}
        </h2>
    </x-slot>
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl">
        <x-card class="bg-yellow-50 border-l-4 border-yellow-400 mb-5">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                            clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-yellow-700">
                        <strong class="font-bold">{{ __('Caution') }}: </strong>
                        <span class="font-medium">{{ __('Changes to these settings may affect your fiscal data integrity. Please proceed with care.') }}</span>
                    </p>
                </div>
            </div>
        </x-card>

        <div class="card-body">
            <div class="card-body">
                <div class="card-title">{{ __('Edit Config') }}</div>
                <x-show-message-bags />

                <x-slot name="header">
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                        {{ __('Configs') }}
                    </h2>
                </x-slot>
                <x-show-message-bags />
                <div class="card-body">
                    <table class="table w-full mt-4 overflow-auto">
                        <thead>
                            <tr>
                                <th class="px-4 py-2">{{ __('Subject') }}</th>
                                <th class="px-4 py-2">{{ __('Value') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($configsTitle as $configTitle)
                                <tr>
                                    <td class="px-4 py-2">{{ $configTitle['label'] ?? '' }}</td>
                                    <td class="px-4 py-2">
                                        {{ $subjects->where('id', config('amir.' . strtolower($configTitle['value'])))->first()?->name ?? __('N/A') }}
                                    </td>
                                    <td class="px-4 py-2">
                                        <a href="{{ route('configs.edit', strtolower($configTitle['value'])) }}" class="btn btn-sm btn-info">{{ __('Edit') }}</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
