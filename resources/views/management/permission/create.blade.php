<x-platform-layout :title="$permission ? __('Edit Permission') : __('Add Permission')" :management-only="true">
    <x-show-message-bags />

    <div class="mb-6 flex items-end justify-between gap-4">
        <div><p class="text-xs font-bold uppercase tracking-[0.16em] text-amber-600 dark:text-amber-400">{{ __('Access control') }}</p><h1 class="mt-1 text-2xl font-bold tracking-tight">{{ $permission ? __('Edit Permission') : __('Create permission') }}</h1><p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ __('Name the capability and choose which roles receive it.') }}</p></div>
        <a href="{{ route('permissions.index') }}" class="btn btn-ghost">{{ __('Back to permissions') }}</a>
    </div>

    <form action="{{ $permission ? route('permissions.update', $permission) : route('permissions.store') }}" method="POST" class="space-y-6">
        @csrf
        @isset($permission) @method('PATCH') @endisset

        <section class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="font-bold">{{ __('Permission information') }}</h2>
            <p class="mt-1 text-xs text-slate-500">{{ __('Use the resource.action naming convention, for example users.edit.') }}</p>
            <div class="mt-4 max-w-2xl"><x-input name="name" id="name" placeholder="{{ __('Permission wildcard') }}" title="{{ __('Permission name') }}" :value="old('name', $permission->name ?? '')" required /></div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <header class="border-b border-slate-200/80 p-5 dark:border-slate-800"><h2 class="font-bold">{{ __('Assigned roles') }}</h2><p class="mt-1 text-xs text-slate-500">{{ __('Select at least one role that should receive this permission.') }}</p></header>
            <div class="grid gap-3 p-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($roles as $role)
                    <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3 dark:border-slate-800 dark:bg-slate-950/30"><x-checkbox title="{{ $role->name }}" name="roles[]" id="roles-{{ $role->id }}" value="{{ $role->id }}" :checked="$syncedRoles->contains($role->id)" /></div>
                @endforeach
            </div>
        </section>

        <div class="flex justify-end gap-2"><a href="{{ route('permissions.index') }}" class="btn btn-ghost">{{ __('Cancel') }}</a><button type="submit" class="btn btn-primary min-w-32">{{ $permission ? __('Save changes') : __('Create permission') }}</button></div>
    </form>
</x-platform-layout>
