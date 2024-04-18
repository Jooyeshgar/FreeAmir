<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Rules') }}
        </h2>
    </x-slot>
    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <span class="card-title">{{ $role ? _('Edit Role') : _('Add Role') }}</span>
            <form action="{{ $role ? route('roles.update', $role->id) : route('roles.store') }}" method="POST">
                @csrf
                @isset($role)
                    @method('PATCH')
                @endisset
                <div class="grid grid-cols-4 gap-5">
                    <div class="col-span-4 md:col-span-2">
                        <input class="input input-bordered w-full" type="text" placeholder="{{ _('Name') }}"
                            id="name" name="name" value="{{ old('name', $role?->name) }}" />
                    </div>
                    <div class="col-span-4">
                        <textarea class="textarea textarea-bordered textarea-lg w-full" plaseholder="{{ _('Description') }}" id="description"
                            name="description">{{ old('description', $role?->description) }}</textarea>
                    </div>
                    <h3 class="col-span-4">{{ _('Permissions') }}</h3>
                    @foreach ($permissions as $permission)
                        <div class="col-span-2 md:col-span-1">
                            <label class="label cursor-pointer">
                                <span class="label-text">{{ $permission->name }}</span>
                                <input type="checkbox" name="permissions[]" value="{{ $permission->id }}"
                                    {{ $syncedPerms->contains($permission->id) ? 'checked' : '' }} class="checkbox" />
                            </label>
                        </div>
                    @endforeach
                    <div class="col-span-4">
                        <button class="btn btn-primary" type="submit">{{ $role ? _('Edit') : _('Add') }}</button>
                    </div>
                </div>
            </form>
        </div>
</x-app-layout>
