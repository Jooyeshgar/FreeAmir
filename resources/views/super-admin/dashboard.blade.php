<x-super-admin-layout :title="__('Dashboard')">
    <x-show-message-bags />

    <section class="admin-rise mb-8 flex flex-col justify-between gap-5 md:flex-row md:items-center" aria-labelledby="management-welcome">
        <div>
            <h2 id="management-welcome"
                class="text-2xl font-extrabold tracking-tight text-[#15263b] sm:text-3xl dark:text-white">
                {{ __('Welcome back, :name', ['name' => auth()->user()->name]) }}
            </h2>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                {{ __('A live overview of the platform during the last 30 days.') }}
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-3 md:justify-end">
            <div class="flex items-center gap-2 whitespace-nowrap text-xs font-semibold text-[#16a394]">
                <span class="h-px w-7 bg-[#16a394]"></span>
                {{ formatDate(now(), 'l، j F Y') }}
            </div>
            <a href="{{ route('management.dashboard') }}"
                class="inline-flex w-fit items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-xs font-bold text-[#15263b] shadow-sm transition hover:border-[#16a394] hover:text-[#16a394] focus:outline-none focus:ring-4 focus:ring-[#16a394]/10 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                        d="M4 4v6h6M20 20v-6h-6M20 9a8 8 0 0 0-14-3L4 10M4 15a8 8 0 0 0 14 3l2-4" />
                </svg>
                {{ __('Refresh') }}
            </a>
        </div>
    </section>

    @if ($metrics['unassignedUsers'] > 0)
        <div role="alert"
            class="mb-5 flex flex-col gap-3 rounded-xl border border-amber-200 bg-amber-50/80 p-4 text-xs text-amber-900 sm:flex-row sm:items-center">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-amber-100 font-bold text-amber-600">!</span>
            <p><b>{{ localizeNumber($metrics['unassignedUsers']) }}</b> {{ __('users have no company assignment.') }}</p>
            <a href="{{ route('users.index') }}"
                class="rounded-lg bg-white px-3 py-2 font-bold text-amber-700 shadow-sm sm:ms-auto">{{ __('Review users') }}</a>
        </div>
    @endif

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5" aria-label="{{ __('Platform metrics') }}">
        <x-kpi-card class="admin-rise" :title="__('New registrations')" :value="$metrics['newUsers']"
            :unit="__('Last 30 days')" :change="$metrics['userGrowthRate']"
            icon="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm7-3h6m-3-3v6" />
        <x-kpi-card class="admin-rise" :title="__('Businesses')" :value="$metrics['businesses']"
            :unit="__('Active: :count', ['count' => localizeNumber($metrics['activeBusinesses'])])"
            icon="M3 21h18M6 21V7l6-4 6 4v14M9 10h1m4 0h1" icon-class="bg-indigo-50 text-indigo-500" />
        <x-kpi-card class="admin-rise" :title="__('Activation rate')" :value="$metrics['activationRate'].'%'"
            :unit="__('Company assigned')" icon="M4 19V9m5 10V5m5 14v-7m5 7V3"
            icon-class="bg-amber-50 text-amber-500" />
        <x-kpi-card class="admin-rise" :title="__('Monthly active users')" :value="$metrics['monthlyActiveUsers']"
            unit="MAU" icon="M3 12h4l3-9 4 18 3-9h4" icon-class="bg-sky-50 text-sky-500" />
        <x-kpi-card class="admin-rise" :title="__('Churn rate')" :value="$metrics['churnRate'].'%'"
            :unit="__('Compared with prior period')" icon="M4 6l6 6 4-4 6 6M4 18h16"
            icon-class="bg-rose-50 text-[#f07662]" />
    </section>

    <section class="mt-5 grid gap-5 xl:grid-cols-[1.65fr_1fr]">
        <x-management.card>
            <header class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="font-bold text-[#15263b] dark:text-white">{{ __('Growth and usage') }}</h2>
                        <span
                            @class(['rounded-full px-2 py-1 text-[10px] font-bold', 'bg-emerald-50 text-emerald-600' => $metrics['userGrowthRate'] >= 0, 'bg-rose-50 text-rose-600' => $metrics['userGrowthRate'] < 0])>MoM {{ localizeNumber(abs($metrics['userGrowthRate'])) }}٪
                            {{ $metrics['userGrowthRate'] >= 0 ? '↑' : '↓' }}</span>
                    </div>
                    <span class="sr-only">{{ __('User growth') }}</span>
                    <p class="mt-1 text-xs text-slate-400">
                        {{ __('Compare registration, new companies, and documents') }}
                    </p>
                </div>
                <div class="flex rounded-xl bg-slate-100 p-1 text-[11px] dark:bg-slate-800" role="tablist">
                    @foreach ([['registrations', __('Registration')], ['companies', __('New company')], ['documents', __('Documents')]] as [$tab, $label])
                        <button type="button" role="tab" data-chart-target="management-growth-chart"
                            data-chart-tab="{{ $tab }}" data-active="{{ $loop->first ? 'true' : 'false' }}"
                            aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                            class="rounded-lg px-3 py-1.5 transition data-[active=true]:bg-white data-[active=true]:font-bold data-[active=true]:text-[#15263b] data-[active=true]:shadow-sm dark:data-[active=true]:bg-slate-700 dark:data-[active=true]:text-white">{{ $label }}</button>
                    @endforeach
                </div>
            </header>
            <x-charts.line-chart chart-id="management-growth-chart" class="mt-7" height-class="h-64"
                :labels="$viewModel['growthLabels']" :tabs="$viewModel['growthTabs']" active-tab="registrations" />
        </x-management.card>
        <x-management.card variant="dark">
            <header class="flex items-center justify-between">
                <div>
                    <h2 class="font-bold">{{ __('Active users') }}</h2>
                    <p class="mt-1 text-xs text-slate-400">{{ __('Current platform engagement') }}</p>
                </div>
                <span class="rounded-lg bg-[#16a394]/15 px-2 py-1 text-[10px] font-bold text-emerald-300">DAU /
                    WAU / MAU</span>
            </header>
            <x-charts.pie-chart chart-id="management-active-users-chart" height-class="mt-5 h-64" :labels="[__('Today'), __('Earlier this week'), __('Earlier this month'), __('Inactive')]" :data="$viewModel['activeUserSegments']"
                :colors="['#16a394', '#f5b94c', '#f07662', '#33455b']" cutout="67%"
                :center-value="$metrics['monthlyActiveUsers']" :center-label="__('Active users')" :label="__('Users')"
                dark />
        </x-management.card>
    </section>

    <section class="mt-5 grid gap-5 xl:grid-cols-[1.4fr_1fr]">
        <x-management.card class="overflow-hidden">
            <header class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="font-bold text-[#15263b] dark:text-white">{{ __('Activation funnel') }}</h2>
                    <p class="mt-1 text-xs text-slate-400">{{ __('From registration to first real value') }}</p>
                </div>
                <div class="text-end">
                    <b class="block text-2xl text-[#15263b] dark:text-white">{{ localizeNumber($viewModel['activationSteps'][3]['percentage']) }}٪</b>
                    <span class="text-[10px] font-medium text-amber-600">{{ __('Activation rate') }}</span>
                </div>
            </header>
            <div class="mt-7 grid gap-3 sm:grid-cols-4">
                @foreach ($viewModel['activationSteps'] as $step)
                    <div class="relative">
                        <div @class(['rounded-xl border p-4', 'border-[#16a394] bg-[#16a394] text-white' => $loop->last, 'border-slate-100 bg-slate-50 text-[#15263b] dark:border-slate-800 dark:bg-slate-950/40 dark:text-white' => !$loop->last])>
                            <div class="flex items-center justify-between"><span class="text-[10px] font-medium">{{ $step['label'] }}</span>
                                <span class="text-[10px] opacity-60">{{ localizeNumber($step['percentage']) }}٪</span>
                            </div>
                            <b class="mt-2 block text-xl">{{ localizeNumber(number_format($step['count'])) }}</b>
                            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-slate-200/60 dark:bg-slate-700">
                                <div @class(['h-full rounded-full', 'bg-white' => $loop->last, 'bg-[#16a394]' => !$loop->last])
                                    style="width: {{ min(100, $step['percentage']) }}%"></div>
                            </div>
                        </div>
                        @unless ($loop->last)
                            <span
                                class="absolute-end-2.5 top-1/2 z-10 hidden h-5 w-5 -translate-y-1/2 place-items-center rounded-full bg-white text-[10px] text-slate-400 shadow sm:grid rtl:rotate-180">→</span>
                        @endunless
                    </div>
                @endforeach
            </div>
            @if ($metrics['unassignedUsers'] > 0)
                <div class="mt-5 flex flex-col gap-3 rounded-xl border border-amber-200 bg-amber-50/70 p-4 sm:flex-row sm:items-center">
                    <div class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-amber-100 text-amber-600">!</div>
                    <p class="text-xs text-amber-900">
                        <b>{{ __(':count users without a company', ['count' => localizeNumber($metrics['unassignedUsers'])]) }}:</b>
                        {{ __('The largest funnel drop is between email verification and company creation.') }}
                    </p>
                    <a href="{{ route('users.index') }}"
                        class="whitespace-nowrap rounded-lg bg-white px-3 py-2 text-[10px] font-bold text-amber-700 shadow-sm sm:ms-auto">{{ __('View users') }}</a>
                </div>
            @endif
        </x-management.card>
        <x-management.card>
            <header>
                <h2 class="font-bold">{{ __('Time to first value') }}</h2>
                <p class="mt-1 text-xs text-slate-400">{{ __('Operational readiness indicators') }}</p>
            </header>
            <div class="mt-8 text-center">
                <b class="text-4xl font-extrabold text-[#15263b] dark:text-white">{{ localizeNumber($metrics['activationRate']) }}٪</b>
                <p class="mt-2 text-xs text-slate-400">{{ __('Users assigned to a company') }}</p>
            </div>
            <div class="mt-7 grid grid-cols-2 divide-x divide-x-reverse divide-slate-100 text-center">
                <div>
                    <b class="block text-lg">{{ localizeNumber($metrics['openFiscalYears']) }}</b>
                    <span class="text-[10px] text-slate-400">{{ __('Open fiscal years') }}</span>
                </div>
                <div>
                    <b class="block text-lg">{{ localizeNumber($metrics['verifiedUsers']) }}</b>
                    <span class="text-[10px] text-slate-400">{{ __('Verified users') }}</span>
                </div>
            </div>
        </x-management.card>
    </section>

    <section class="mt-5 grid gap-5 xl:grid-cols-[1.2fr_1fr]">
        <x-management.card>
            <header>
                <h2 class="font-bold">{{ __('Engagement and retention') }}</h2>
                <p class="mt-1 text-xs text-slate-400">{{ __('Activity across key time windows') }}</p>
            </header>
            <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach([['DAU', $metrics['dailyActiveUsers']], ['WAU', $metrics['weeklyActiveUsers']], ['MAU', $metrics['monthlyActiveUsers']], [__('Active businesses'), $metrics['activeBusinesses']]] as $item)
                    <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-950/40">
                        <span class="text-[10px] text-slate-400">{{ $item[0] }}</span>
                        <b class="mt-1 block text-xl">{{ localizeNumber(number_format($item[1])) }}</b>
                    </div>
                @endforeach
            </div>
            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl border border-slate-100 p-4">
                    <span class="text-[10px] text-slate-400">{{ __('DAU/MAU stickiness') }}</span>
                    <b class="mt-1 block text-lg">{{ localizeNumber($metrics['monthlyActiveUsers'] > 0 ? round(($metrics['dailyActiveUsers'] / $metrics['monthlyActiveUsers']) * 100, 1) : 0) }}٪</b>
                </div>
                <div class="rounded-xl border border-slate-100 p-4">
                    <span class="text-[10px] text-slate-400">{{ __('Documents this month') }}</span>
                        <b class="mt-1 block text-lg">{{ localizeNumber(number_format($metrics['monthlyDocuments'])) }}</b>
                </div>
                <div class="rounded-xl border border-rose-100 bg-rose-50/50 p-4">
                    <span class="text-[10px] text-rose-500">{{ __('Churn rate') }}</span>
                    <b class="mt-1 block text-lg text-rose-600">{{ localizeNumber($metrics['churnRate']) }}٪</b>
                </div>
            </div>
        </x-management.card>
        <x-management.card>
            <header>
                <h2 class="font-bold">{{ __('Retention cohort') }}</h2>
                <p class="mt-1 text-xs text-slate-400">
                    {{ __('Activity trend') }} · {{ __('Activity volume over the last seven days') }}
                </p>
            </header>
            <div class="mt-6 grid grid-cols-7 gap-2">
            @foreach($activityTrend as $day)
                <div class="text-center">
                    <div class="grid h-16 place-items-center rounded-lg bg-[#16a394] text-[10px] font-bold text-white"
                        style="opacity: {{ max(.18, $day['count'] / $viewModel['maximumActivity']) }}">
                        {{ localizeNumber($day['count']) }}
                    </div>
                    <span class="mt-2 block text-[9px] text-slate-400">
                    {{ __($day['label']) }}
                    </span>
                </div>
            @endforeach
            </div>
        </x-management.card>
    </section>

    <section class="mt-5 grid gap-5 lg:grid-cols-3">
        <x-management.card class="lg:col-span-2">
            <header class="flex items-center justify-between">
                <div>
                    <h2 class="font-bold">{{ __('Usage depth') }}</h2>
                    <p class="mt-1 text-xs text-slate-400">
                    {{ __('Companies with the highest usage and usage trend') }}
                    </p>
                </div>
                <div class="text-end">
                    <b class="text-lg">{{ localizeNumber($metrics['documentsPerActiveBusiness']) }}</b>
                    <span class="block text-[10px] text-slate-400">{{ __('Documents / active business') }}</span>
                </div>
            </header>
            <div class="mt-5 grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-950/40">
                    <span class="text-[10px] text-slate-400">{{ __('Documents this month') }}</span>
                    <b class="mt-1 block text-2xl">{{ localizeNumber(number_format($metrics['monthlyDocuments'])) }}</b>
                </div>
                <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-950/40">
                    <span class="text-[10px] text-slate-400">{{ __('Invoices this month') }}</span>
                    <b class="mt-1 block text-2xl">{{ localizeNumber(number_format($metrics['monthlyInvoices'])) }}</b>
                </div>
                <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-950/40">
                    <span class="text-[10px] text-slate-400">{{ __('Activity records') }}</span>
                    <b class="mt-1 block text-2xl">{{ localizeNumber(number_format($activityMetrics['total'])) }}</b>
                </div>
            </div>
            <div class="mt-6 grid gap-6 border-t border-slate-100 pt-5 sm:grid-cols-2 dark:border-slate-800">
                <div>
                    <p class="mb-3 text-[10px] font-bold text-slate-400">{{ __('Highest usage') }}</p>
                    @forelse ($topUsageCompanies as $company)
                        <div class="mb-3 flex items-center gap-3 text-xs">
                            <span class="w-24 truncate" title="{{ $company['name'] }}">{{ $company['name'] }}</span>
                            <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <div class="h-full rounded-full bg-[#16a394]" style="width: {{ $company['percentage'] }}%">
                                </div>
                            </div>
                            <b class="w-12 text-end">{{ localizeNumber(number_format($company['documents'])) }}</b>
                        </div>
                    @empty
                        <p class="rounded-lg bg-slate-50 px-3 py-4 text-center text-xs text-slate-400 dark:bg-slate-950/40">
                            {{ __('No usage data for this month.') }}</p>
                    @endforelse
                </div>
                <div>
                    <p class="mb-3 text-[10px] font-bold text-rose-500">
                        {{ __('Usage decline compared with last month') }}</p>
                    @forelse ($fallingUsageCompanies as $company)
                        <div
                            class="mb-3 flex items-center justify-between rounded-lg bg-rose-50/60 px-3 py-2 text-xs dark:bg-rose-950/20">
                            <span class="min-w-0 truncate" title="{{ $company['name'] }}">{{ $company['name'] }}</span><b
                                class="ms-3 shrink-0 text-rose-600 dark:text-rose-400">−{{ localizeNumber($company['drop']) }}٪</b>
                        </div>
                    @empty
                        <p class="rounded-lg bg-slate-50 px-3 py-4 text-center text-xs text-slate-400 dark:bg-slate-950/40">
                            {{ __('No usage declines compared with last month.') }}</p>
                    @endforelse
                </div>
            </div>
        </x-management.card>
        <x-management.card variant="dark">
            <header class="flex items-center justify-between">
                <div>
                    <h2 class="font-bold">{{ __('System health') }}</h2>
                    <p class="mt-1 text-xs text-slate-400">{{ __('Current application status') }}</p>
                </div>
                <span class="flex items-center gap-1.5 text-[10px] text-emerald-300">
                <i
                        class="h-2 w-2 rounded-full bg-emerald-400"></i>{{ __('Stable') }}
                        </span>
            </header>
            <div class="mt-5 space-y-3">
                @foreach([[__('Environment'), __(config('app.env'))], [__('Version'), localizeNumber(config('app.version'))], [__('Registration'), config('app.registration') ? __('Enabled') : __('Disabled')], [__('Email verification'), config('app.email_verification') ? __('Enabled') : __('Disabled')]] as $health)
                    <div class="flex items-center justify-between rounded-xl bg-white/5 p-3">
                    <span
                            class="text-xs text-slate-300">{{ $health[0] }}</span>
                            <b class="text-sm">{{ $health[1] }}</b>
                </div>@endforeach
            </div>
        </x-management.card>
    </section>

    <section class="mt-5 grid gap-5 xl:grid-cols-[1.65fr_1fr]">
        <x-management.card class="overflow-hidden" :padded="false">
            <header
                class="flex items-center justify-between border-b border-slate-100 p-5 sm:px-6 dark:border-slate-800">
                <div>
                    <h2 class="font-bold">{{ __('Latest fiscal years') }}</h2>
                    <p class="mt-1 text-xs text-slate-400">{{ __('Recently added company records') }}</p>
                </div>
                <a href="{{ route('companies.index') }}" class="text-xs font-bold text-[#16a394]">
                    {{ __('View all') }} ←
                </a>
            </header>
            <div class="scrollbar overflow-x-auto">
                <table class="table min-w-[620px]">
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
                                <td class="font-bold">{{ $company->name }}</td>
                                <td>{{ localizeNumber($company->fiscal_year) }}</td>
                                <td>{{ localizeNumber($company->users_count) }}</td>
                                <td><span @class(['rounded-full px-2.5 py-1 text-[10px]', 'bg-emerald-50 text-emerald-600' => !$company->closed_at, 'bg-slate-100 text-slate-500' => $company->closed_at])>{{ $company->closed_at ? __('Closed') : __('Open') }}</span>
                                </td>
                                <td><a href="{{ route('companies.edit', $company) }}"
                                        class="text-xs text-[#16a394]">{{ __('Edit') }}</a></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-10 text-center">
                                    {{ __('No companies have been created yet.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-management.card>
        <x-management.card :padded="false">
            <header class="border-b border-slate-100 p-5 dark:border-slate-800">
                <h2 class="font-bold">{{ __('Users by role') }}</h2>
                <p class="mt-1 text-xs text-slate-400">{{ __('Current access distribution') }}</p>
            </header>
            <div class="p-5">
                @if ($roles->isNotEmpty())
                    <x-charts.bar-chart chart-id="management-users-by-role-chart" height-class="h-64"
                        :datas="$viewModel['roleChartData']" :label="__('Users')" background-color="#16a394"
                        border-color="#118579" datalabel-color="#16a394" />
                @else
                    <p class="py-8 text-center text-xs text-slate-400">{{ __('No roles are configured.') }}</p>
                @endif
                <a href="{{ route('roles.index') }}"
                    class="mt-4 block rounded-xl border border-slate-200 px-3 py-2 text-center text-xs font-bold dark:border-slate-700">
                    {{ __('Manage roles') }}
                </a>
            </div>
        </x-management.card>
    </section>

    <section class="mt-5 grid gap-5 xl:grid-cols-[1.65fr_1fr]">
        <x-management.card class="overflow-hidden" :padded="false">
            <header
                class="flex items-center justify-between border-b border-slate-100 p-5 sm:px-6 dark:border-slate-800">
                <div>
                    <h2 class="font-bold">{{ __('New users') }}</h2>
                    <p class="mt-1 text-xs text-slate-400">{{ __('Newest platform accounts') }}</p>
                </div>
                <a href="{{ route('users.index') }}"
                    class="text-xs font-bold text-[#16a394]">{{ __('View all') }} ←</a>
            </header>
            <div class="scrollbar overflow-x-auto">
                <table class="table min-w-[760px]">
                    <thead>
                        <tr>
                            <th>{{ __('User') }}</th>
                            <th>{{ __('Role') }}</th>
                            <th>{{ __('Companies') }}</th>
                            <th>{{ __('Verification') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentUsers as $recentUser)
                            <tr>
                            <td><a href="{{ route('users.show', $recentUser) }}"
                                    class="font-bold">{{ $recentUser->name }}</a>
                                <span class="block text-[10px] text-slate-400">{{ $recentUser->email }}</span>
                            </td>
                            <td>{{ $recentUser->roles->pluck('name')->join('، ') ?: __('No role') }}</td>
                            <td>{{ localizeNumber($recentUser->companies_count) }}</td>
                            <td><span @class(['rounded-full px-2.5 py-1 text-[10px]', 'bg-emerald-50 text-emerald-600' => $recentUser->hasVerifiedEmail(), 'bg-amber-50 text-amber-600' => !$recentUser->hasVerifiedEmail()])>{{ $recentUser->hasVerifiedEmail() ? __('Verified') : __('Pending') }}</span>
                            </td>
                            <td>
                                <div class="flex items-center justify-end gap-2">
                                    @unless ($recentUser->hasVerifiedEmail())
                                        <form action="{{ route('users.verify', $recentUser) }}" method="post">
                                            @csrf
                                            <button class="text-[10px] text-emerald-600">{{ __('Verify') }}</button>
                                        </form>
                                    @endunless
                                    @if (auth()->user()->canImpersonateUser($recentUser))
                                        <form action="{{ route('users.impersonate', $recentUser) }}" method="post">
                                            @csrf
                                            <button class="text-[10px] text-violet-600">{{ __('Impersonate') }}</button>
                                        </form>
                                    @else
                                        <span class="text-[10px] text-slate-300"
                                            title="{{ (int) $recentUser->companies_count === 0 ? __('User has no company') : __('Impersonation is not available for this user.') }}">
                                            {{ (int) $recentUser->companies_count === 0 ? __('User has no company') : __('Impersonate') }}
                                        </span>
                                    @endif
                                    <a href="{{ route('users.edit', $recentUser) }}"
                                        class="text-[10px] text-[#16a394]">{{ __('Edit') }}</a>
                                </div>
                            </td>
                            </tr>
                        @empty
                            <tr>
                            <td colspan="5" class="py-10 text-center">{{ __('No users have been created yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-management.card>
        <div class="space-y-5">
            <x-management.card>
                <h2 class="font-bold">{{ __('Quick access') }}</h2>
                <div class="mt-4 grid grid-cols-2 gap-3">
                    @foreach([[__('Add user'), route('users.create'), 'M12 5v14M5 12h14'], [__('Companies'), route('companies.index'), 'M3 21h18M6 21V7l6-4 6 4v14'], [__('Access control'), route('roles.index'), 'M12 3 4 7v5c0 5 3.4 8 8 9'], [__('Settings'), route('management.settings'), 'M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z']] as $action)
                        <a href="{{ $action[1] }}"
                            class="rounded-xl border border-slate-100 bg-slate-50 p-3 transition hover:border-[#16a394]/30 hover:bg-[#16a394]/5 dark:border-slate-800 dark:bg-slate-950/40"><span
                                class="grid h-9 w-9 place-items-center rounded-lg bg-[#16a394]/10 text-[#16a394]"><svg
                                    class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-width="1.8" d="{{ $action[2] }}" />
                                </svg>
                    </span><b class="mt-3 block text-xs">{{ $action[0] }}</b></a>@endforeach
                </div>
            </x-management.card>
            <x-management.card>
                <div class="flex justify-between">
                    <div>
                        <h2 class="font-bold">{{ __('Fiscal year status') }}</h2>
                        <p class="mt-1 text-[10px] text-slate-400">{{ __('Open and closed periods') }}</p>
                    </div><a href="{{ route('companies.index') }}"
                        class="text-[10px] text-[#16a394]">{{ __('View all') }}</a>
                </div>
                <div class="mt-5 grid grid-cols-2 text-center">
                    <div><b class="block text-xl">{{ localizeNumber($metrics['openFiscalYears']) }}</b>
                        <span class="text-[10px] text-slate-400">{{ __('Open') }}</span>
                    </div>
                    <div class="border-s border-slate-100">
                        <b class="block text-xl">{{ localizeNumber($metrics['closedFiscalYears']) }}</b><span
                            class="text-[10px] text-slate-400">{{ __('Closed') }}</span>
                    </div>
                </div>
            </x-management.card>
        </div>
    </section>
</x-super-admin-layout>
