<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Configs') }}
        </h2>
    </x-slot>
    <x-show-message-bags/>
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="card-actions">
                <a href="{{ route('configs.create') }}" class="btn btn-primary">Create & Edit Config</a>

                <table class="table w-full mt-4 overflow-auto">
                    <thead>
                    <tr>
                        <th class="px-4 py-2">کلید</th>
                        <th class="px-4 py-2">مقدار</th>
                        <th class="px-4 py-2">Action</th>
                    </tr>
                    </thead>
                    <tbody>

                    @foreach ($configs as $config)
                        <tr>
                            <td class="px-4 py-2">{{ $config->key }}</td>
                            <td class="px-4 py-2">
                                @if($config->key === 'co_logo')
                                    <img class="w-12 h-auto rounded-full" src="{{ asset("storage/$config->value") }}" alt="{{ $config->value }}">
                                @else
                                    {{ $config->value }}
                                @endif
                            </td>
                            <td class="px-4 py-2">
{{--                                <a href="{{ route('configs.edit', $config) }}"--}}
{{--                                    class="btn btn-sm btn-info">Edit</a>--}}
                                <form action="{{ route('configs.destroy', $config) }}" method="POST"
                                        class="inline-block">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-error">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                {{ $configs->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
