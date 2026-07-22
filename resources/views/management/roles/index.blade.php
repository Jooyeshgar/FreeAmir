<x-platform-layout :title="__('Roles')" :management-only="true">
    <x-show-message-bags />

    <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-sky-600 dark:text-sky-400">{{ __('Access control') }}</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight">{{ __('Roles and access policies') }}</h1>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ __('Define reusable access policies and review their assignments.') }}</p>
        </div>
        @can('roles.create')
            <a href="{{ route('roles.create') }}" class="btn btn-primary gap-2 rounded-xl shadow-lg shadow-primary/15"><span class="text-lg leading-none">+</span>{{ __('Add new Role') }}</a>
        @endcan
    </div>

    <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-col justify-between gap-2 border-b border-slate-200/80 p-3 dark:border-slate-800 sm:flex-row sm:items-center">
            <form action="{{ route('roles.index') }}" method="GET" class="flex w-full min-w-0 flex-nowrap items-center gap-1.5 overflow-x-auto pb-1 sm:flex-1">
                <label class="input input-bordered flex h-9 w-[19.5rem] max-w-full shrink-0 items-center gap-1.5 rounded-lg bg-slate-50 text-sm dark:bg-slate-950/50 sm:w-[21rem]">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-4.35-4.35M19 11a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z" /></svg>
                    <input type="search" name="search" value="{{ request('search') }}" class="grow" placeholder="{{ __('Search roles') }}">
                </label>
                <button type="submit" class="btn btn-neutral btn-sm h-9 min-h-9 shrink-0 rounded-lg px-3">{{ __('Search') }}</button>
                @if (request('search'))<a href="{{ route('roles.index') }}" class="btn btn-ghost btn-sm h-9 min-h-9 shrink-0 rounded-lg px-3">{{ __('Clear') }}</a>@endif
            </form>
            <span class="whitespace-nowrap text-xs font-medium text-slate-500 dark:text-slate-400">{{ trans_choice(':count role|:count roles', $roles->total(), ['count' => localizeNumber(number_format($roles->total()))]) }}</span>
        </div>

        <div class="overflow-x-auto">
            <table class="table table-lg">
                <thead class="bg-slate-50/80 dark:bg-slate-950/30"><tr><th>{{ __('Role') }}</th><th>{{ __('Granted permissions') }}</th><th>{{ __('Assigned users') }}</th><th>{{ __('Guard') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @forelse ($roles as $role)
                        <tr class="border-slate-100 transition-colors hover:bg-slate-50/70 dark:border-slate-800 dark:hover:bg-slate-800/30">
                            <td>
                                <div class="flex min-w-60 items-center gap-3">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-100 font-bold text-sky-700 dark:bg-sky-950 dark:text-sky-300">{{ mb_strtoupper(mb_substr($role->name, 0, 1)) }}</span>
                                    <div><p class="font-semibold" dir="ltr">{{ $role->name }}</p><p class="mt-0.5 text-xs text-slate-500">{{ __('Access policy') }}</p></div>
                                </div>
                            </td>
                            <td><span class="badge badge-info badge-outline">{{ localizeNumber(number_format($role->permissions_count)) }}</span></td>
                            <td><span class="badge badge-ghost">{{ localizeNumber(number_format($role->users_count)) }}</span></td>
                            <td><span class="rounded-lg bg-slate-100 px-2.5 py-1 font-mono text-xs dark:bg-slate-800">{{ $role->guard_name }}</span></td>
                            <td><div class="flex justify-end gap-1">
                                @can('roles.edit')<a class="btn btn-ghost btn-sm" href="{{ route('roles.edit', $role) }}">{{ __('Edit') }}</a>@endcan
                                @can('roles.destroy')<form action="{{ route('roles.destroy', $role) }}" method="POST" onsubmit="return confirm('{{ __('Are you sure?') }}')">@csrf @method('DELETE')<button type="submit" class="btn btn-ghost btn-sm text-error">{{ __('Delete') }}</button></form>@endcan
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-20 text-center"><p class="font-semibold text-slate-600 dark:text-slate-300">{{ __('No roles found.') }}</p><p class="mt-1 text-sm text-slate-400">{{ __('Try changing your search or create a role.') }}</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($roles->hasPages())<div class="border-t border-slate-200 p-4 dark:border-slate-800">{{ $roles->links() }}</div>@endif
    </section>
</x-platform-layout>
