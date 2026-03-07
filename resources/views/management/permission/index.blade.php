<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Permissions') }}
        </h2>
    </x-slot>
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="flex flex-wrap items-center gap-3 mb-4">
                <span class="card-title">{{ __('Permissions') }}</span>
                <a href="{{ route('permissions.create') }}">
                    <button class="btn btn-primary btn-sm">{{ __('Add new Permission') }}</button>
                </a>
                <form class="flex items-center" method="GET" action="{{ route('permissions.index') }}">
                    <x-input name="search" placeholder="{{ __('Search') }}" :value="request('search')" title="" />
                </form>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3">
                @foreach ($permissions as $permission)
                    <div class="card bg-base-200 shadow border border-base-300">
                        <div class="card-body p-3">
                            <p class="font-mono text-xs font-semibold break-all" style="direction: ltr">
                                {{ $permission->name }}
                            </p>
                            <span class="badge badge-ghost badge-sm">{{ $permission->guard_name }}</span>
                            <div class="card-actions justify-end mt-2">
                                <a class="btn btn-xs btn-info" href="{{ route('permissions.edit', $permission->id) }}">
                                    {{ __('Edit') }}
                                </a>
                                <form action="{{ route('permissions.destroy', $permission->id) }}" method="post" class="inline mb-0">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-xs btn-error" type="submit" onclick="return confirm('{{ __('Are you sure?') }}')">
                                        {{ __('Delete') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-4">
                {!! $permissions->withQueryString()->links() !!}
            </div>
        </div>
    </div>
</x-app-layout>
