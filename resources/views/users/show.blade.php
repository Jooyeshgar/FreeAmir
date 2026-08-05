<x-platform-layout :title="__('User Details') . ' - ' . $user->name">
    <x-show-message-bags />

    <div class="mb-4">
        <a href="{{ route('users.index') }}" class="group inline-flex items-center gap-2 rounded-lg px-1 py-1 text-sm font-medium text-slate-500 transition hover:text-indigo-700 dark:text-slate-400 dark:hover:text-indigo-300">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform group-hover:-translate-x-0.5 rtl:rotate-180 rtl:group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 19-7-7 7-7" /></svg>
            {{ __('Back to users') }}
        </a>
    </div>

    <section class="relative overflow-hidden rounded-3xl border border-slate-600/30 p-5 dark:border-slate-700/50 dark:from-slate-900 dark:via-slate-950 dark:to-black sm:p-6">
        <div class="pointer-events-none absolute -end-16 -top-24 h-72 w-72 rounded-full border-[42px] border-white/5"></div>
        <div class="pointer-events-none absolute -bottom-24 start-1/3 h-56 w-56 rounded-full bg-indigo-300/5 blur-2xl"></div>

        <div class="relative flex flex-col justify-between gap-6 lg:flex-row lg:items-end">
            <div class="flex min-w-0 items-center gap-4 sm:gap-5">
                <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl border border-white/15 bg-white/10 text-2xl font-bold ring-1 ring-white/5 backdrop-blur sm:h-18 sm:w-18 sm:text-3xl">
                    {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                </div>
                <div class="min-w-0">
                    <div class="mb-2 flex flex-wrap items-center gap-2">
                        <span @class([
                            'badge border font-semibold shadow-sm',
                            'border-emerald-300/40 bg-emerald-500 text-white' => $user->hasVerifiedEmail(),
                            'border-amber-300/50 bg-amber-400 text-amber-950' => ! $user->hasVerifiedEmail(),
                        ])>
                            <span class="me-1 h-1.5 w-1.5 rounded-full bg-current opacity-80"></span>
                            {{ $user->hasVerifiedEmail() ? __('Verified') : __('Pending') }}
                        </span>
                        <span class="rounded-full border border-white/10 bg-black/15 px-2.5 py-1 text-xs font-medium text-slate-100">{{ __('User ID') }}: {{ localizeNumber($user->id) }}</span>
                    </div>
                    <h1 class="truncate text-2xl font-bold tracking-tight sm:text-3xl">{{ $user->name }}</h1>
                    <p class="mt-1 truncate text-sm text-slate-100/80">{{ $user->email }}</p>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                @if (! $user->hasVerifiedEmail() && auth()->user()->can('access-super-admin-panel'))
                    <form action="{{ route('users.verify', $user) }}" method="post" onsubmit="return confirm('{{ __('Are you sure you want to verify this user?') }}')">
                        @csrf
                        <button type="submit" class="btn btn-sm gap-1.5 border-emerald-500 bg-emerald-500 text-white shadow-sm shadow-emerald-950/20 hover:border-emerald-400 hover:bg-emerald-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m5 12 4 4L19 6" /></svg>
                            {{ __('Verify') }}
                        </button>
                    </form>
                @endif
                @if (auth()->user()->canImpersonateUser($user))
                    <form action="{{ route('users.impersonate', $user) }}" method="post" onsubmit="return confirm('{{ __('Are you sure you want to impersonate this user?') }}')">
                        @csrf
                        <button type="submit" class="btn btn-sm gap-1.5 border-indigo-500 bg-indigo-500 text-white shadow-sm shadow-indigo-950/20 hover:border-indigo-400 hover:bg-indigo-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 19a6 6 0 0 0-12 0m6-8a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm12 8a6 6 0 0 0-9-5.2M17 3.1a4 4 0 0 1 0 7.8" /></svg>
                            {{ __('Impersonate') }}
                        </button>
                    </form>
                @endif
                @can('users.edit')
                    <a href="{{ route('users.edit', $user) }}" class="btn btn-sm gap-1.5 border border-slate-200 bg-slate-100 text-slate-800 shadow-sm hover:border-white hover:bg-white">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m16.9 3.1 4 4L7.5 20.5 3 21l.5-4.5L16.9 3.1Z" /></svg>
                        {{ __('Edit') }}
                    </a>
                @endcan
                @can('users.destroy')
                    <form action="{{ route('users.destroy', $user) }}" method="post" onsubmit="return confirm('{{ __('Are you sure you want to delete this user?') }}')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm gap-1.5 border border-rose-500 bg-rose-500 text-white shadow-sm shadow-rose-950/20 hover:border-rose-400 hover:bg-rose-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7h16m-10 4v6m4-6v6m-7 4h10a2 2 0 0 0 2-2V7H5v12a2 2 0 0 0 2 2Zm3-14V4h4v3" /></svg>
                            {{ __('Delete') }}
                        </button>
                    </form>
                @endcan
            </div>
        </div>
    </section>

    <section class="mt-6 grid gap-4 sm:grid-cols-3" aria-label="{{ __('Profile overview') }}">
        <article class="rounded-2xl border border-emerald-200/70 bg-linear-to-br from-white to-emerald-50/60 p-5 dark:border-emerald-900/60 dark:from-slate-900 dark:to-emerald-950/20">
            <p class="text-xs font-bold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">{{ __('Verification status') }}</p>
            <div class="mt-3 flex items-center justify-between gap-3">
                <strong @class(['text-lg', 'text-emerald-600 dark:text-emerald-400' => $user->hasVerifiedEmail(), 'text-amber-600 dark:text-amber-400' => ! $user->hasVerifiedEmail()])>{{ $user->hasVerifiedEmail() ? __('Verified') : __('Pending') }}</strong>
                <span @class(['rounded-xl p-2.5', 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' => $user->hasVerifiedEmail(), 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300' => ! $user->hasVerifiedEmail()])>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m9 12 2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                </span>
            </div>
        </article>

        <article class="rounded-2xl border border-sky-200/70 bg-linear-to-br from-white to-sky-50/60 p-5 dark:border-sky-900/60 dark:from-slate-900 dark:to-sky-950/20">
            <p class="text-xs font-bold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">{{ __('Assigned companies') }}</p>
            <div class="mt-3 flex items-center justify-between gap-3">
                <strong class="text-2xl font-bold text-slate-900 dark:text-white">{{ localizeNumber(number_format($user->companies->count())) }}</strong>
                <span class="rounded-xl bg-sky-100 p-2.5 text-sky-700 dark:bg-sky-950 dark:text-sky-300">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 21h18M5 21V7l7-4 7 4v14M9 9h.01M9 13h.01M9 17h.01M15 9h.01M15 13h.01M15 17h.01" /></svg>
                </span>
            </div>
        </article>

        <article class="rounded-2xl border border-indigo-200/70 bg-linear-to-br from-white to-indigo-50/60 p-5 dark:border-indigo-900/60 dark:from-slate-900 dark:to-indigo-950/20">
            <p class="text-xs font-bold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">{{ __('Assigned roles') }}</p>
            <div class="mt-3 flex items-start justify-between gap-3">
                <div class="flex flex-wrap gap-2">
                    @forelse ($user->roles as $role)
                        <span class="badge border-indigo-200 bg-indigo-100 font-semibold text-indigo-800 dark:border-indigo-800 dark:bg-indigo-950 dark:text-indigo-200">{{ $role->name }}</span>
                    @empty
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('No roles assigned') }}</p>
                    @endforelse
                </div>
                <span class="rounded-xl bg-indigo-100 p-2.5 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m7-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm10 10v-2a4 4 0 0 0-3-3.87m-2-11.96a4 4 0 0 1 0 7.75" /></svg>
                </span>
            </div>
        </article>
    </section>

    <section class="mt-6 space-y-6">
        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white/95 dark:border-slate-800 dark:bg-slate-900/95">
            <header class="flex flex-col gap-1 border-b border-slate-200 px-5 py-4 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="font-bold text-slate-900 dark:text-white">{{ __('Assigned companies') }}</h2>
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ trans_choice(':count company|:count companies', $user->companies->count(), ['count' => localizeNumber(number_format($user->companies->count()))]) }}</p>
            </header>

            <div class="overflow-x-auto">
                <table class="table min-w-[64rem] text-slate-700 dark:text-slate-200">
                    <thead class="bg-slate-50/90 text-slate-600 dark:bg-slate-950/70 dark:text-slate-300">
                        <tr class="border-slate-200 dark:border-slate-800">
                            <th scope="col">{{ __('Company name') }}</th>
                            <th scope="col">{{ __('Fiscal year') }}</th>
                            <th scope="col">{{ __('Currency') }}</th>
                            <th scope="col">{{ __('National Code') }}</th>
                            <th scope="col">{{ __('Contact') }}</th>
                            <th scope="col">{{ __('Status') }}</th>
                            <th><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($user->companies as $company)
                            <tr class="border-slate-200 align-top transition-colors odd:bg-white even:bg-slate-50/70 hover:bg-indigo-50/70 dark:border-slate-800 dark:odd:bg-slate-900 dark:even:bg-slate-950/40 dark:hover:bg-indigo-950/25">
                                <td class="min-w-52">
                                    <p class="font-medium text-slate-900 dark:text-slate-100">{{ $company->name }}</p>
                                    <p class="mt-1 max-w-56 truncate text-xs text-slate-500">{{ $company->address ? localizeNumber($company->address) : __('No address') }}</p>
                                </td>
                                <td class="whitespace-nowrap font-medium">{{ localizeNumber($company->fiscal_year) }}</td>
                                <td class="whitespace-nowrap">{{ __($company->currency) }}</td>
                                <td class="min-w-40">
                                    <p class="font-medium text-slate-800 dark:text-slate-100">{{ $company->national_code ?: '—' }}</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                        {{ __('Economic code') }}:
                                        <span>{{ $company->economical_code ?: '—' }}</span>
                                    </p>
                                </td>
                                <td class="min-w-40">
                                    <p class="font-medium text-slate-800 dark:text-slate-100">{{ $company->phone_number ?: '—' }}</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                        {{ __('Postal code') }}:
                                        <span>{{ $company->postal_code ?: '—' }}</span>
                                    </p>
                                </td>
                                <td>
                                    <span @class([
                                        'badge badge-sm whitespace-nowrap',
                                        'border-emerald-200 bg-emerald-100 font-semibold text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200' => ! $company->closed_at,
                                        'border-slate-300 bg-slate-100 font-semibold text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200' => $company->closed_at,
                                    ])>{{ $company->closed_at ? __('Closed') : __('Open') }}</span>
                                </td>
                                <td class="text-end">
                                    @can('companies.edit')
                                        <a href="{{ route('companies.edit', $company) }}" class="btn btn-xs whitespace-nowrap border-indigo-200 bg-indigo-50 text-indigo-700 hover:border-indigo-300 hover:bg-indigo-100 dark:border-indigo-800 dark:bg-indigo-950/70 dark:text-indigo-200 dark:hover:bg-indigo-900/70">{{ __('Edit company') }}</a>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr class="bg-slate-50/60 dark:bg-slate-950/30">
                                <td colspan="7" class="px-6 py-14 text-center">
                                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-slate-800">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 21h18M5 21V7l7-4 7 4v14" /></svg>
                                    </div>
                                    <p class="mt-3 font-medium text-slate-600 dark:text-white">{{ __('No companies assigned') }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>

        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white/95 dark:border-slate-800 dark:bg-slate-900/95">
            <header class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <h2 class="font-bold text-slate-900 dark:text-white">{{ __('Account timeline') }}</h2>
            </header>
            <dl class="grid gap-3 p-4 sm:grid-cols-3">
                <div class="flex items-center gap-3 rounded-xl bg-slate-50 p-4 dark:bg-slate-950/50">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 2v3m8-3v3M3 9h18M5 4h14a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" /></svg>
                    </span>
                    <div class="min-w-0">
                        <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Created at') }}</dt>
                        <dd class="mt-1 truncate text-sm font-semibold text-slate-800 dark:text-slate-100"><time datetime="{{ $user->created_at->toIso8601String() }}">{{ formatDateTime($user->created_at) }}</time></dd>
                    </div>
                </div>
                <div class="flex items-center gap-3 rounded-xl bg-slate-50 p-4 dark:bg-slate-950/50">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 11a8 8 0 1 0-2.34 5.66M20 4v7h-7" /></svg>
                    </span>
                    <div class="min-w-0">
                        <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Last updated') }}</dt>
                        <dd class="mt-1 truncate text-sm font-semibold text-slate-800 dark:text-slate-100"><time datetime="{{ $user->updated_at->toIso8601String() }}">{{ formatDateTime($user->updated_at) }}</time></dd>
                    </div>
                </div>
                <div class="flex items-center gap-3 rounded-xl bg-slate-50 p-4 dark:bg-slate-950/50">
                    <span @class([
                        'flex h-10 w-10 shrink-0 items-center justify-center rounded-xl',
                        'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' => $user->email_verified_at,
                        'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300' => ! $user->email_verified_at,
                    ])>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m9 12 2 2 4-4m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                    </span>
                    <div class="min-w-0">
                        <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Verified at') }}</dt>
                        <dd class="mt-1 truncate text-sm font-semibold text-slate-800 dark:text-slate-100">
                            @if ($user->email_verified_at)
                                <time datetime="{{ $user->email_verified_at->toIso8601String() }}">{{ formatDateTime($user->email_verified_at) }}</time>
                            @else
                                {{ __('Never') }}
                            @endif
                        </dd>
                    </div>
                </div>
            </dl>
        </article>
    </section>
</x-platform-layout>
