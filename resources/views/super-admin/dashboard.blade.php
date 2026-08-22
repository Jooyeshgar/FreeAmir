<x-super-admin-layout :title="__('Dashboard')">
    <x-show-message-bags />

    <section class="relative mb-7 overflow-hidden rounded-3xl bg-linear-to-br from-[#073f36] via-[#0b594b] to-[#087f68] p-6 text-white shadow-xl shadow-emerald-950/15 sm:p-8">
        <div class="pointer-events-none absolute -end-16 -top-20 h-64 w-64 rounded-full border-[36px] border-white/5"></div>
        <div class="pointer-events-none absolute -bottom-24 end-1/3 h-52 w-52 rounded-full bg-emerald-300/10 blur-2xl"></div>
        <div class="relative flex flex-col justify-between gap-6 sm:flex-row sm:items-end">
            <div>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ __('Platform overview') }}</h2>
                <p class="mt-2 max-w-2xl text-sm leading-7 text-emerald-50/75">{{ __('Manage companies, fiscal years, users, and access from one place.') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('users.create') }}" class="btn border-0 bg-white text-emerald-900 hover:bg-emerald-50">{{ __('Add New User') }}</a>
            </div>
        </div>
    </section>

    @if ($metrics['unassignedUsers'] > 0)
        <div role="alert" class="alert alert-warning mb-6 border border-warning/30 bg-warning/10 text-slate-800 dark:text-slate-100">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3Z" />
            </svg>
            <span>{{ trans_choice(':count user has no company assignment.|:count users have no company assignment.', $metrics['unassignedUsers'], ['count' => localizeNumber($metrics['unassignedUsers'])]) }}</span>
            <a href="{{ route('users.index') }}" class="btn btn-sm btn-ghost">{{ __('Review users') }}</a>
        </div>
    @endif

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="{{ __('Platform metrics') }}">
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Businesses') }}</p>
                    <strong class="mt-3 block text-3xl font-bold">{{ localizeNumber(number_format($metrics['businesses'])) }}</strong>
                </div>
                <span class="rounded-xl bg-emerald-100 p-3 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 21h18M5 21V7l7-4 7 4v14M9 9h.01M9 13h.01M9 17h.01M15 9h.01M15 13h.01M15 17h.01" /></svg>
                </span>
            </div>
            <a href="{{ route('companies.index') }}" class="mt-5 inline-flex text-xs font-semibold text-emerald-700 hover:underline dark:text-emerald-400">{{ __('Manage companies') }}</a>
        </article>

        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Fiscal years') }}</p>
                    <strong class="mt-3 block text-3xl font-bold">{{ localizeNumber(number_format($metrics['fiscalYears'])) }}</strong>
                </div>
                <span class="rounded-xl bg-sky-100 p-3 text-sky-700 dark:bg-sky-950 dark:text-sky-300">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3M5 11h14M5 5h14a2 2 0 0 1 2 2v13H3V7a2 2 0 0 1 2-2Z" /></svg>
                </span>
            </div>
            <p class="mt-5 text-xs text-slate-500 dark:text-slate-400">{{ __(':count open fiscal years', ['count' => localizeNumber(number_format($metrics['openFiscalYears']))]) }}</p>
        </article>

        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Users') }}</p>
                    <strong class="mt-3 block text-3xl font-bold">{{ localizeNumber(number_format($metrics['users'])) }}</strong>
                </div>
                <span class="rounded-xl bg-violet-100 p-3 text-violet-700 dark:bg-violet-950 dark:text-violet-300">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m7-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.87m-2-11.96a4 4 0 0 1 0 7.75" /></svg>
                </span>
            </div>
            <a href="{{ route('users.index') }}" class="mt-5 inline-flex text-xs font-semibold text-violet-700 hover:underline dark:text-violet-400">{{ __('Manage users') }}</a>
        </article>

        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ __('Verified users') }}</p>
                    <strong class="mt-3 block text-3xl font-bold">{{ localizeNumber(number_format($metrics['verifiedUsers'])) }}</strong>
                </div>
                <span class="rounded-xl bg-amber-100 p-3 text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m9 12 2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                </span>
            </div>
            @php($verificationRate = $metrics['users'] > 0 ? round(($metrics['verifiedUsers'] / $metrics['users']) * 100) : 0)
            <div class="mt-5 flex items-center gap-3">
                <progress class="progress progress-warning h-2" value="{{ $verificationRate }}" max="100"></progress>
                <span class="text-xs font-semibold">{{ __(':rate%', ['rate' => localizeNumber($verificationRate)]) }}</span>
            </div>
        </article>
    </section>

    @php($maxUserGrowth = max(1, $userGrowth->max('count')))
    @php($maxActivity = max(1, $activityTrend->max('count')))
    <section class="mt-6 grid gap-6 xl:grid-cols-2" aria-label="{{ __('Managerial trends') }}">
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <header class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="font-bold">{{ __('User growth') }}</h3>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('New accounts during the last six months') }}</p>
                </div>
                <div class="text-end">
                    <strong class="block text-2xl text-violet-700 dark:text-violet-300">{{ localizeNumber(number_format($metrics['newUsers'])) }}</strong>
                    <span class="text-xs text-slate-500">{{ __('Last 30 days') }}</span>
                </div>
            </header>
            <div class="mt-2 text-xs font-semibold {{ $metrics['userGrowthRate'] >= 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-error' }}">
                {{ $metrics['userGrowthRate'] >= 0 ? '+' : '' }}{{ localizeNumber($metrics['userGrowthRate']) }}% {{ __('compared with the previous period') }}
            </div>
            <div class="mt-6 flex h-44 items-end gap-3" role="img" aria-label="{{ __('Monthly new-user trend') }}">
                @foreach ($userGrowth as $month)
                    <div class="flex h-full min-w-0 flex-1 flex-col justify-end gap-2 text-center">
                        <span class="text-xs font-semibold">{{ localizeNumber($month['count']) }}</span>
                        <div class="mx-auto w-full max-w-12 rounded-t-lg bg-violet-500 dark:bg-violet-400" style="height: {{ max(4, round(($month['count'] / $maxUserGrowth) * 116)) }}px"></div>
                        <span class="truncate text-xs text-slate-500">{{ __($month['label']) }}</span>
                    </div>
                @endforeach
            </div>
        </article>

        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <header class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="font-bold">{{ __('Activity trend') }}</h3>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Recorded platform events during the last seven days') }}</p>
                </div>
                <div class="text-end">
                    <strong class="block text-2xl text-emerald-700 dark:text-emerald-300">{{ localizeNumber(number_format($activityMetrics['today'])) }}</strong>
                    <span class="text-xs text-slate-500">{{ __('Today') }}</span>
                </div>
            </header>
            <div class="mt-6 flex h-48 items-end gap-2" role="img" aria-label="{{ __('Daily activity trend') }}">
                @foreach ($activityTrend as $day)
                    <div class="flex h-full min-w-0 flex-1 flex-col justify-end gap-2 text-center">
                        <span class="text-xs font-semibold">{{ localizeNumber($day['count']) }}</span>
                        <div class="mx-auto w-full max-w-10 rounded-t-lg bg-emerald-500 dark:bg-emerald-400" style="height: {{ max(4, round(($day['count'] / $maxActivity) * 132)) }}px"></div>
                        <span class="truncate text-xs text-slate-500">{{ __($day['label']) }}</span>
                    </div>
                @endforeach
            </div>
        </article>
    </section>

    <section class="mt-6 grid gap-4 sm:grid-cols-2" aria-label="{{ __('Operational status') }}">
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-between gap-3"><h3 class="font-bold">{{ __('Active businesses') }}</h3><strong class="text-2xl text-emerald-700 dark:text-emerald-300">{{ localizeNumber(number_format($metrics['activeBusinesses'])) }}</strong></div>
            @php($activeBusinessRate = $metrics['businesses'] > 0 ? round(($metrics['activeBusinesses'] / $metrics['businesses']) * 100) : 0)
            <progress class="progress progress-success mt-4 h-2" value="{{ $activeBusinessRate }}" max="100"></progress>
            <p class="mt-2 text-xs text-slate-500">{{ __('Businesses with at least one open fiscal year') }}</p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-between gap-3"><h3 class="font-bold">{{ __('Fiscal year status') }}</h3><strong class="text-2xl text-sky-700 dark:text-sky-300">{{ localizeNumber(number_format($metrics['openFiscalYears'])) }}</strong></div>
            <div class="mt-4 flex h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                @php($openFiscalRate = $metrics['fiscalYears'] > 0 ? round(($metrics['openFiscalYears'] / $metrics['fiscalYears']) * 100) : 0)
                <span class="bg-sky-500" style="width: {{ $openFiscalRate }}%"></span>
            </div>
            <p class="mt-2 text-xs text-slate-500">{{ __(':open open, :closed closed', ['open' => localizeNumber($metrics['openFiscalYears']), 'closed' => localizeNumber($metrics['closedFiscalYears'])]) }}</p>
        </article>
    </section>

    <section class="mt-6 grid gap-6 xl:grid-cols-3">
        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900 xl:col-span-2">
            <header class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <div>
                    <h3 class="font-bold">{{ __('Latest fiscal years') }}</h3>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Recently added company records') }}</p>
                </div>
                <a href="{{ route('companies.index') }}" class="btn btn-ghost btn-sm">{{ __('View all') }}</a>
            </header>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('Company') }}</th>
                            <th>{{ __('Fiscal year') }}</th>
                            <th>{{ __('Users') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentCompanies as $company)
                            <tr>
                                <td class="font-semibold">{{ $company->name }}</td>
                                <td>{{ localizeNumber($company->fiscal_year) }}</td>
                                <td>{{ localizeNumber(number_format($company->users_count)) }}</td>
                                <td>
                                    <span @class(['badge badge-sm', 'badge-success badge-outline' => ! $company->closed_at, 'badge-ghost' => $company->closed_at])>
                                        {{ $company->closed_at ? __('Closed') : __('Open') }}
                                    </span>
                                </td>
                                <td><a href="{{ route('companies.edit', $company) }}" class="btn btn-ghost btn-xs">{{ __('Edit') }}</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-10 text-center text-slate-500">{{ __('No companies have been created yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>

        <article class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <header class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <h3 class="font-bold">{{ __('Users by role') }}</h3>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Current access distribution') }}</p>
            </header>
            <div class="space-y-5 p-5">
                @forelse ($roles as $role)
                    @php($roleRate = $metrics['users'] > 0 ? min(100, round(($role->users_count / $metrics['users']) * 100)) : 0)
                    <div>
                        <div class="mb-2 flex items-center justify-between gap-3 text-sm">
                            <span class="truncate font-medium">{{ $role->name }}</span>
                            <span class="text-xs text-slate-500 dark:text-slate-400">{{ localizeNumber(number_format($role->users_count)) }}</span>
                        </div>
                        <progress class="progress progress-success h-1.5" value="{{ $roleRate }}" max="100"></progress>
                    </div>
                @empty
                    <p class="py-8 text-center text-sm text-slate-500">{{ __('No roles are configured.') }}</p>
                @endforelse
                <a href="{{ route('roles.index') }}" class="btn btn-outline btn-sm btn-block">{{ __('Manage roles') }}</a>
            </div>
        </article>
    </section>

    <section class="mt-6 grid gap-6 xl:grid-cols-3">
        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900 xl:col-span-2">
            <header class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <div>
                    <h3 class="font-bold">{{ __('Recently added users') }}</h3>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Newest platform accounts') }}</p>
                </div>
                <a href="{{ route('users.index') }}" class="btn btn-ghost btn-sm">{{ __('View all') }}</a>
            </header>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>{{ __('User') }}</th><th>{{ __('Role') }}</th><th>{{ __('Companies') }}</th><th>{{ __('Verification') }}</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($recentUsers as $user)
                            <tr>
                                <td>
                                    <a href="{{ route('users.show', $user) }}" class="font-semibold text-violet-700 hover:underline dark:text-violet-400">{{ $user->name }}</a>
                                    <div class="mt-0.5 text-xs text-slate-500">{{ $user->email }}</div>
                                </td>
                                <td><span class="text-sm">{{ $user->roles->pluck('name')->join('، ') ?: __('No role') }}</span></td>
                                <td>{{ localizeNumber(number_format($user->companies_count)) }}</td>
                                <td>
                                    <span @class(['badge badge-sm', 'badge-success badge-outline' => $user->hasVerifiedEmail(), 'badge-warning badge-outline' => ! $user->hasVerifiedEmail()])>
                                        {{ $user->hasVerifiedEmail() ? __('Verified') : __('Pending') }}
                                    </span>
                                </td>
                                <td>
                                    <div class="flex justify-end gap-1">
                                        @unless ($user->hasVerifiedEmail())
                                            <form action="{{ route('users.verify', $user) }}" method="post" onsubmit="return confirm('{{ __('Are you sure you want to verify this user?') }}')">
                                                @csrf
                                                <button type="submit" class="btn btn-ghost btn-xs text-success">{{ __('Verify') }}</button>
                                            </form>
                                        @endunless
                                        @if (auth()->user()->canImpersonateUser($user))
                                            <form action="{{ route('users.impersonate', $user) }}" method="post" onsubmit="return confirm('{{ __('Are you sure you want to impersonate this user?') }}')">
                                                @csrf
                                                <button type="submit" class="btn btn-ghost btn-xs text-violet-600 dark:text-violet-400">{{ __('Impersonate') }}</button>
                                            </form>
                                        @else
                                            @php($impersonationUnavailableReason = (int) $user->companies_count === 0 ? __('User has no company') : __('Impersonation is not available for this user.'))
                                            <span class="tooltip" data-tip="{{ $impersonationUnavailableReason }}">
                                                <button type="button" disabled aria-disabled="true" title="{{ $impersonationUnavailableReason }}" class="btn btn-ghost btn-xs btn-disabled cursor-not-allowed">{{ __('Impersonate') }}</button>
                                            </span>
                                        @endif
                                        <a href="{{ route('users.edit', $user) }}" class="btn btn-ghost btn-xs">{{ __('Edit') }}</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-10 text-center text-slate-500">{{ __('No users have been created yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>

        <article class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <header class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <h3 class="font-bold">{{ __('System configuration') }}</h3>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Current application mode') }}</p>
            </header>
            <dl class="divide-y divide-slate-100 px-5 dark:divide-slate-800">
                <div class="flex items-center justify-between gap-4 py-4"><dt class="text-sm text-slate-500">{{ __('Environment') }}</dt><dd class="badge badge-ghost">{{ __(config('app.env')) }}</dd></div>
                <div class="flex items-center justify-between gap-4 py-4"><dt class="text-sm text-slate-500">{{ __('Version') }}</dt><dd class="text-sm font-semibold">{{ localizeNumber(config('app.version')) }}</dd></div>
                <div class="flex items-center justify-between gap-4 py-4"><dt class="text-sm text-slate-500">{{ __('Registration') }}</dt><dd class="badge {{ config('app.registration') ? 'badge-success badge-outline' : 'badge-ghost' }}">{{ config('app.registration') ? __('Enabled') : __('Disabled') }}</dd></div>
                <div class="flex items-center justify-between gap-4 py-4"><dt class="text-sm text-slate-500">{{ __('Email verification') }}</dt><dd class="badge {{ config('app.email_verification') ? 'badge-success badge-outline' : 'badge-ghost' }}">{{ config('app.email_verification') ? __('Enabled') : __('Disabled') }}</dd></div>
            </dl>
            <div class="p-5 pt-3"><a href="{{ route('management.settings') }}" class="btn btn-outline btn-sm btn-block">{{ __('Settings') }}</a></div>
        </article>
    </section>

    <section class="mt-6 grid gap-6 xl:grid-cols-3" aria-label="{{ __('Activity log summary') }}">
        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900 xl:col-span-2">
            <header class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <div>
                    <h3 class="font-bold">{{ __('Recent activity') }}</h3>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Latest user actions and model changes across the platform.') }}</p>
                </div>
                <a href="{{ route('management.activity-logs.index') }}" class="btn btn-ghost btn-sm">{{ __('Show all') }}</a>
            </header>

            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($recentActivities as $activity)
                    @php($details = $activity->details ?? collect())
                    @php($isRequest = $activity->source === 'request')
                    @php($actionClasses = ['created' => 'badge-success badge-outline', 'updated' => 'badge-info badge-outline', 'deleted' => 'badge-error badge-outline'])
                    @php($actionClass = $actionClasses[$activity->action] ?? 'badge-ghost')
                    @php($activitySubject = $isRequest ? ($details->get('route') ?: $activity->description) : class_basename($activity->model_type).' · '.$details->get('model_label', '#'.$activity->model_id))

                    <div class="flex flex-col justify-between gap-3 px-5 py-4 sm:flex-row sm:items-center">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                {{ mb_strtoupper(mb_substr($activity->user?->name ?? '?', 0, 1)) }}
                            </span>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="truncate text-sm font-semibold">{{ $activity->user?->name ?? __('System') }}</span>
                                    <span class="badge badge-sm {{ $actionClass }}">{{ __(ucfirst($activity->action ?? 'activity')) }}</span>
                                </div>
                                <p class="mt-1 truncate text-xs text-slate-500" dir="ltr">{{ $activitySubject }}</p>
                            </div>
                        </div>
                        <time datetime="{{ $activity->created_at?->toAtomString() }}" class="shrink-0 text-xs text-slate-500">
                            {{ formatDateTime($activity->created_at) }}
                        </time>
                    </div>
                @empty
                    <div class="px-6 py-14 text-center">
                        <p class="font-semibold">{{ __('No activity has been recorded yet.') }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ __('User actions and model changes will appear here.') }}</p>
                    </div>
                @endforelse
            </div>
        </article>

        <article class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <header class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <div>
                    <h3 class="font-bold">{{ __('Events') }}</h3>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Platform activity overview') }}</p>
                </div>
                <span @class(['badge badge-sm', 'badge-success badge-outline' => config('activitylog.enabled'), 'badge-error badge-outline' => ! config('activitylog.enabled')])>
                    {{ config('activitylog.enabled') ? __('Enabled') : __('Disabled') }}
                </span>
            </header>

            <dl class="divide-y divide-slate-100 px-5 dark:divide-slate-800">
                <div class="flex items-center justify-between gap-4 py-4"><dt class="text-sm text-slate-500">{{ __('Events today') }}</dt><dd class="text-lg font-bold text-emerald-700 dark:text-emerald-300">{{ localizeNumber(number_format($activityMetrics['today'])) }}</dd></div>
                <div class="flex items-center justify-between gap-4 py-4"><dt class="text-sm text-slate-500">{{ __('Model events') }}</dt><dd class="text-sm font-semibold">{{ localizeNumber(number_format($activityMetrics['model'])) }}</dd></div>
                <div class="flex items-center justify-between gap-4 py-4"><dt class="text-sm text-slate-500">{{ __('Request events') }}</dt><dd class="text-sm font-semibold">{{ localizeNumber(number_format($activityMetrics['request'])) }}</dd></div>
                <div class="flex items-center justify-between gap-4 py-4"><dt class="text-sm text-slate-500">{{ __('Total records') }}</dt><dd class="text-sm font-semibold">{{ localizeNumber(number_format($activityMetrics['total'])) }}</dd></div>
            </dl>
            <div class="p-5 pt-3"><a href="{{ route('management.activity-logs.index') }}" class="btn btn-outline btn-sm btn-block">{{ __('Open activity log') }}</a></div>
        </article>
    </section>
</x-super-admin-layout>
