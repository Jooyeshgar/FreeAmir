<x-platform-layout :title="__('Permissions')" :management-only="true">
    <x-show-message-bags />

    <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div><p class="text-xs font-bold uppercase tracking-[0.16em] text-amber-600 dark:text-amber-400">{{ __('Access control') }}</p><h1 class="mt-1 text-2xl font-bold tracking-tight">{{ __('Permission catalog') }}</h1><p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ __('Review individual capabilities and the roles that receive them.') }}</p></div>
        @can('permissions.create')<a href="{{ route('permissions.create') }}" class="btn btn-primary gap-2 shadow-lg shadow-primary/15"><span class="text-lg leading-none">+</span>{{ __('Add new Permission') }}</a>@endcan
    </div>

    <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-col justify-between gap-2 border-b border-slate-200/80 p-3 dark:border-slate-800 sm:flex-row sm:items-center">
            <form action="{{ route('permissions.index') }}" method="GET" class="flex w-full min-w-0 flex-nowrap items-center gap-1.5 overflow-x-auto pb-1 sm:flex-1">
                <label class="input input-bordered flex h-9 w-[21rem] max-w-full shrink-0 items-center gap-1.5 rounded-lg bg-slate-50 text-sm dark:bg-slate-950/50 sm:w-[22.5rem]"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-4.35-4.35M19 11a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z" /></svg><input type="search" name="search" value="{{ request('search') }}" class="grow" placeholder="{{ __('Search permissions') }}"></label>
                <button type="submit" class="btn btn-neutral btn-sm h-9 min-h-9 shrink-0 rounded-lg px-3">{{ __('Search') }}</button>
                @if (request('search'))<a href="{{ route('permissions.index') }}" class="btn btn-ghost btn-sm h-9 min-h-9 shrink-0 rounded-lg px-3">{{ __('Clear') }}</a>@endif
            </form>
            <span class="whitespace-nowrap text-xs font-medium text-slate-500">{{ trans_choice(':count permission|:count permissions', $permissions->total(), ['count' => localizeNumber(number_format($permissions->total()))]) }}</span>
        </div>

        <div class="grid gap-3 p-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @forelse ($permissions as $permission)
                <article class="group flex min-h-40 flex-col rounded-xl border border-slate-200 bg-slate-50/60 p-4 transition-all hover:-translate-y-0.5 hover:border-amber-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-950/30 dark:hover:border-amber-800">
                    <div class="flex items-start justify-between gap-3"><span class="rounded-lg bg-amber-100 px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-amber-700 dark:bg-amber-950 dark:text-amber-300">{{ str($permission->name)->before('.') }}</span><span class="badge badge-ghost badge-sm">{{ $permission->guard_name }}</span></div>
                    <h2 class="mt-4 break-all font-mono text-sm font-semibold" dir="ltr">{{ $permission->name }}</h2>
                    <p class="mt-2 text-xs text-slate-500">{{ trans_choice('Assigned to :count role|Assigned to :count roles', $permission->roles_count, ['count' => localizeNumber(number_format($permission->roles_count))]) }}</p>
                    <div class="mt-auto flex justify-end gap-1 pt-4">
                        @can('permissions.edit')<a class="btn btn-ghost btn-xs" href="{{ route('permissions.edit', $permission) }}">{{ __('Edit') }}</a>@endcan
                        @can('permissions.destroy')<form action="{{ route('permissions.destroy', $permission) }}" method="POST" onsubmit="return confirm('{{ __('Are you sure?') }}')">@csrf @method('DELETE')<button class="btn btn-ghost btn-xs text-error" type="submit">{{ __('Delete') }}</button></form>@endcan
                    </div>
                </article>
            @empty
                <div class="col-span-full py-16 text-center"><p class="font-semibold text-slate-600 dark:text-slate-300">{{ __('No permissions found.') }}</p><p class="mt-1 text-sm text-slate-400">{{ __('Try changing your search or create a permission.') }}</p></div>
            @endforelse
        </div>
        @if ($permissions->hasPages())<div class="border-t border-slate-200 p-4 dark:border-slate-800">{{ $permissions->links() }}</div>@endif
    </section>
</x-platform-layout>
