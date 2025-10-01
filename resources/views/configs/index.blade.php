<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Configs') }}
        </h2>
    </x-slot>
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl">
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
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <table class="table w-full mt-4 overflow-auto">
                            <thead>
                                <tr>
                                    <th class="px-4 py-2">{{ __('Subject') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($configs as $config)
                                    <tr>
                                        <td class="px-4 py-2">{{ $config->desc ?? '' }}</td>
                                        <td class="px-4 py-2">
                                            <a href="{{ route('configs.edit', $config->id) }}"
                                                class="btn btn-sm btn-info">{{ __('Edit') }}</a>
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