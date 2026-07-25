<x-platform-layout :title="__('Users')">
    <x-show-message-bags />

    <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-violet-600 dark:text-violet-400">{{ __('Identity and access') }}</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight">{{ __('Platform users') }}</h2>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ __('Manage accounts, company assignments, and access roles.') }}</p>
        </div>
        @can('users.create')
            <a href="{{ route('users.create') }}" class="btn btn-primary gap-2 rounded-xl shadow-lg shadow-primary/15"><span class="text-lg leading-none">+</span>{{ __('Add New User') }}</a>
        @endcan
    </div>

    <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-col justify-between gap-2 border-b border-slate-200/80 p-3 dark:border-slate-800 sm:flex-row sm:items-center">
            <form action="{{ route('users.index') }}" method="GET" class="flex w-full min-w-0 flex-nowrap items-center gap-1.5 overflow-x-auto pb-1 sm:flex-1">
                <label class="input input-bordered flex h-9 w-[21rem] max-w-full shrink-0 items-center gap-1.5 rounded-lg bg-slate-50 text-sm dark:bg-slate-950/50 sm:w-[22.5rem]">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-4.35-4.35M19 11a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z" /></svg>
                    <input type="search" name="search" value="{{ request('search') }}" class="grow" placeholder="{{ __('Search name or email') }}">
                </label>
                <select name="verification" class="select select-bordered h-9 min-h-9 w-36 shrink-0 rounded-lg bg-slate-50 text-sm dark:bg-slate-950/50" aria-label="{{ __('Verification') }}">
                    <option value="">{{ __('All accounts') }}</option>
                    <option value="verified" @selected(request('verification') === 'verified')>{{ __('Verified') }}</option>
                    <option value="pending" @selected(request('verification') === 'pending')>{{ __('Pending') }}</option>
                </select>
                <button class="btn btn-neutral btn-sm h-9 min-h-9 shrink-0 rounded-lg px-3" type="submit">{{ __('Filter') }}</button>
                @if (request()->hasAny(['search', 'verification']))
                    <a href="{{ route('users.index') }}" class="btn btn-ghost btn-sm h-9 min-h-9 shrink-0 rounded-lg px-3">{{ __('Clear') }}</a>
                @endif
            </form>
            <span class="whitespace-nowrap text-xs font-medium text-slate-500 dark:text-slate-400">{{ trans_choice(':count account|:count accounts', $users->total(), ['count' => localizeNumber(number_format($users->total()))]) }}</span>
        </div>

        <div class="overflow-x-auto">
            <table class="table table-lg">
                <thead class="bg-slate-50/80 dark:bg-slate-950/30"><tr><th>{{ __('User') }}</th><th>{{ __('Roles') }}</th><th>{{ __('Companies') }}</th><th>{{ __('Verification') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr class="border-slate-100 transition-colors hover:bg-slate-50/70 dark:border-slate-800 dark:hover:bg-slate-800/30">
                            <td>
                                <div class="flex min-w-56 items-center gap-3">
                                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-linear-to-br from-violet-100 to-indigo-100 font-bold text-violet-700 dark:from-violet-950 dark:to-indigo-950 dark:text-violet-300">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                                    <div><p class="font-semibold text-slate-900 dark:text-white">{{ $user->name }}</p><p class="mt-0.5 text-xs text-slate-500" dir="ltr">{{ $user->email }}</p></div>
                                </div>
                            </td>
                            <td><div class="flex max-w-64 flex-wrap gap-1">@forelse ($user->roles as $role)<span class="badge badge-ghost badge-sm">{{ $role->name }}</span>@empty<span class="text-xs text-slate-400">{{ __('No role') }}</span>@endforelse</div></td>
                            <td><span class="badge badge-ghost">{{ localizeNumber(number_format($user->companies_count)) }}</span></td>
                            <td><span @class(['badge', 'badge-success badge-outline' => $user->hasVerifiedEmail(), 'badge-warning badge-outline' => ! $user->hasVerifiedEmail()])>{{ $user->hasVerifiedEmail() ? __('Verified') : __('Pending') }}</span></td>
                            <td>
                                <div class="flex justify-end gap-1">
                                    @can('users.show')<a href="{{ route('users.show', $user) }}" class="btn btn-ghost btn-sm rounded-lg">{{ __('View') }}</a>@endcan
                                    @can('users.edit')<a href="{{ route('users.edit', $user) }}" class="btn btn-ghost btn-sm rounded-lg">{{ __('Edit') }}</a>@endcan
                                    @if (auth()->user()->canImpersonateUser($user))
                                        <form action="{{ route('users.impersonate', $user) }}" method="post" onsubmit="return confirm('{{ __('Are you sure you want to impersonate this user?') }}')">
                                            @csrf
                                            <button type="submit" class="btn btn-ghost btn-sm rounded-lg text-violet-600 dark:text-violet-400">{{ __('Impersonate') }}</button>
                                        </form>
                                    @elseif (! $user->hasVerifiedEmail() && auth()->user()->canImpersonateUserIfVerified($user))
                                        <span class="tooltip" data-tip="{{ __('User is not verified') }}">
                                            <button type="button" disabled title="{{ __('User is not verified') }}" class="btn btn-ghost btn-sm btn-disabled cursor-not-allowed rounded-lg">{{ __('Impersonate') }}</button>
                                        </span>
                                    @endif
                                    @cannot('access-super-admin-panel')
                                        @if ($user->employee)
                                            @canany(['hr.employees.show', 'users.show'])<a href="{{ route('hr.employees.show', $user->employee) }}" class="btn btn-ghost btn-sm rounded-lg">{{ __('View Employee') }}</a>@endcanany
                                        @else
                                            @can('users.create-employee')<form action="{{ route('users.create-employee', $user) }}" method="post">@csrf<button type="submit" class="btn btn-ghost btn-sm rounded-lg">{{ __('Create Employee') }}</button></form>@endcan
                                        @endif
                                    @endcannot
                                    @can('users.destroy')<form action="{{ route('users.destroy', $user) }}" method="post" onsubmit="return confirm('{{ __('Are you sure you want to delete this user?') }}')">@csrf @method('DELETE')<button type="submit" class="btn btn-ghost btn-sm rounded-lg text-error">{{ __('Delete') }}</button></form>@endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-20 text-center"><p class="font-semibold text-slate-600 dark:text-slate-300">{{ __('No users found.') }}</p><p class="mt-1 text-sm text-slate-400">{{ __('Try changing your filters or add a user.') }}</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())<div class="border-t border-slate-200 p-4 dark:border-slate-800">{{ $users->links() }}</div>@endif
    </section>
</x-platform-layout>
