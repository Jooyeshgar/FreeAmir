<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Permissions') }}
        </h2>
    </x-slot>
    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <span class="card-title">{{ $permission ? __('Edit Permission') : __('Add Permission') }}</span>
            <form action="{{ $permission ? route('permissions.update', $permission->id) : route('permissions.store') }}"
                method="POST">
                @csrf
                @isset($permission)
                    @method('PATCH')
                @endisset

                <div class="grid grid-cols-4 gap-5">
                    <div class="col-span-4 md:col-span-2">
                        <label class="input input-bordered flex items-center gap-2">
                            <input type="text" id="name" name="name" placeholder="Name"
                                class="w-4 h-4 opacity-70" value="{{ old('name', $permission?->name) }}" required>
                        </label>
                    </div>
                    <div></div>
                    <div class="col-span-4">
                        <textarea name="description" id="description" placeholder="{{ __('Description') }}"
                            class="textarea textarea-bordered textarea-lg w-full">{{ old('description', $permission?->description) }}</textarea>
                    </div>

                    <h3 class="col-span-4 text-lg font-semibold mb-2">{{ __('Roles') }}</h3>
                    @foreach ($roles as $role)
                        <div class="col-span-2 md:col-span-1">
                            <label class="label cursor-pointer">
                                <span class="label-text">{{ $role->name }}</span>
                                <input type="checkbox" name="roles[]" value="{{ $role->id }}"
                                    {{ $syncedRoles->contains($role->id) ? 'checked' : '' }} class="checkbox" />
                            </label>
                        </div>
                    @endforeach
                    <div class="col-span-4">
                        <button type="submit"
                            class="btn btn-primary">{{ $permission ? __('Edit') : __('Add') }}</button>
                    </div>
                </div>

            </form>
        </div>
    </div>
</x-app-layout>
