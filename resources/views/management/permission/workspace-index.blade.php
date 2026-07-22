<x-app-layout :title="__('Permissions')">
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <form action="{{ route('permissions.index') }}" method="GET" class="flex flex-wrap items-center gap-2">
                <div class="w-60 [&_.input]:input-sm">
                    <x-input name="search" placeholder="{{ __('Permission wildcard') }}" :value="request('search')" title="" />
                </div>
                <button type="submit" class="btn btn-sm btn-neutral">{{ __('Search') }}</button>
            </form>
            <div class="card-actions">
                @can('permissions.create')
                    <a href="{{ route('permissions.create') }}" class="btn btn-primary">{{ __('Add new Permission') }}</a>
                @endcan
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
                                @can('permissions.edit')
                                    <a class="btn btn-xs btn-info" href="{{ route('permissions.edit', $permission->id) }}">
                                        {{ __('Edit') }}
                                    </a>
                                @endcan
                                @can('permissions.destroy')
                                    <form action="{{ route('permissions.destroy', $permission->id) }}" method="post" class="inline mb-0">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-xs btn-error" type="submit" onclick="return confirm('{{ __('Are you sure?') }}')">
                                            {{ __('Delete') }}
                                        </button>
                                    </form>
                                @endcan
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
