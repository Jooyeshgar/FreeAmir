<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Rules') }}
        </h2>
    </x-slot>
    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <span class="card-title">{{ $role ? __('Edit Role') : __('Add Role') }}</span>
            <form action="{{ $role ? route('roles.update', $role->id) : route('roles.store') }}" method="POST">
                @csrf
                @isset($role)
                    @method('PATCH')
                @endisset
                <div class="grid grid-cols-4 gap-5">
                    <div class="col-span-4 md:col-span-2">
                        <x-input name="name" id="name" placeholder="{{ __('Role Wildcard') }}" title=""
                            :value="old('name', $role?->name ?? '')" />
                    </div>
                    <div class="col-span-4">
                        <x-textarea title="{{ __('Description') }}" id="description" name="description"
                            :value="old('description', $role?->description ?? '')" />
                    </div>
                    <h3 class="col-span-4">{{ __('Permissions') }}</h3>
                    @foreach ($permissions as $permission)
                        <div class="col-span-2 md:col-span-1">
                            <x-checkbox title="{{ $permission->name }}" name="permissions[]" id="permissions-{{ $permission->id }}"
                                value="{{ $permission->id }}" :checked="$syncedPerms->contains($permission->id)" />
                        </div>
                    @endforeach
                    <div class="col-span-4">
                        <button class="btn btn-primary" type="submit">{{ $role ? __('Edit') : __('Add') }}</button>
                    </div>
                </div>
            </form>
        </div>
</x-app-layout>
