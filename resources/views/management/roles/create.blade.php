<x-platform-layout :title="$role ? __('Edit Role') : __('Add Role')" :management-only="true">
    <x-show-message-bags />

    <div class="mb-6 flex items-end justify-between gap-4">
        <div><p class="text-xs font-bold uppercase tracking-[0.16em] text-sky-600 dark:text-sky-400">{{ __('Access control') }}</p><h1 class="mt-1 text-2xl font-bold tracking-tight">{{ $role ? __('Edit Role') : __('Create role') }}</h1><p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ __('Choose a clear role name and grant only the permissions it requires.') }}</p></div>
        <a href="{{ route('roles.index') }}" class="btn btn-ghost">{{ __('Back to roles') }}</a>
    </div>

    <form action="{{ $role ? route('roles.update', $role) : route('roles.store') }}" method="POST" x-data="{ permissionSearch: '' }" class="space-y-6">
        @csrf
        @isset($role) @method('PATCH') @endisset

        <section class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="font-bold">{{ __('Role information') }}</h2>
            <div class="mt-4 max-w-2xl"><x-input name="name" id="name" placeholder="{{ __('Role name') }}" title="{{ __('Name') }}" :value="old('name', $role?->name ?? '')" required /></div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <header class="flex flex-col justify-between gap-3 border-b border-slate-200/80 p-5 dark:border-slate-800 sm:flex-row sm:items-center">
                <div><h2 class="font-bold">{{ __('Granted permissions') }}</h2><p class="mt-1 text-xs text-slate-500">{{ trans_choice(':count permission available|:count permissions available', $permissions->count(), ['count' => localizeNumber(number_format($permissions->count()))]) }}</p></div>
                <label class="input input-bordered flex h-10 items-center gap-2 rounded-xl bg-slate-50 dark:bg-slate-950/50 sm:w-72"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-4.35-4.35M19 11a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z" /></svg><input x-model="permissionSearch" type="search" class="grow" placeholder="{{ __('Search permissions') }}"></label>
            </header>
            <div class="grid gap-3 p-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($permissions as $permission)
                    <div x-show="@js(strtolower($permission->name)).includes(permissionSearch.toLowerCase())" class="rounded-xl border border-slate-200 bg-slate-50/70 p-3 dark:border-slate-800 dark:bg-slate-950/30">
                        <x-checkbox title="{{ $permission->name }}" name="permissions[]" id="permissions-{{ $permission->id }}" value="{{ $permission->id }}" :checked="$syncedPerms->contains($permission->id)" />
                    </div>
                @endforeach
            </div>
        </section>

        <div class="flex justify-end gap-2"><a href="{{ route('roles.index') }}" class="btn btn-ghost">{{ __('Cancel') }}</a><button class="btn btn-primary min-w-32" type="submit">{{ $role ? __('Save changes') : __('Create role') }}</button></div>
    </form>
</x-platform-layout>
