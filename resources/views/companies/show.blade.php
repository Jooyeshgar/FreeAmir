<x-platform-layout :title="__('Company overview') . ' - ' . $business->name">
    <x-show-message-bags />

    <div class="mb-4">
        <a href="{{ route('companies.index') }}" class="group inline-flex items-center gap-2 rounded-lg px-1 py-1 text-sm font-medium text-slate-500 transition hover:text-emerald-700 dark:text-slate-400 dark:hover:text-emerald-300">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform group-hover:-translate-x-0.5 rtl:rotate-180 rtl:group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 19-7-7 7-7" /></svg>
            {{ __('Back to Companies') }}
        </a>
    </div>

    <section class="relative overflow-hidden rounded-3xl border border-emerald-200/70 bg-slate-950 p-5 text-white shadow-xl shadow-emerald-950/10 sm:p-7">
        <div class="pointer-events-none absolute inset-y-0 end-0 w-2/5 bg-linear-to-l from-emerald-500/20 to-transparent"></div>
        <div class="pointer-events-none absolute -end-12 -top-20 h-64 w-64 rounded-full border-[38px] border-emerald-300/10"></div>

        <div class="relative flex flex-col justify-between gap-7 lg:flex-row lg:items-end">
            <div class="min-w-0">
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-emerald-300">{{ __('Company overview') }}</p>
                <h1 class="mt-2 truncate text-3xl font-black tracking-tight sm:text-4xl">{{ $business->name }}</h1>
                <div class="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-xs text-slate-300">
                    <span>{{ __('National Code') }}: <bdi class="font-mono text-white" dir="ltr">{{ $business->national_code ?: '—' }}</bdi></span>
                    <span>{{ __('Economical Code') }}: <bdi class="font-mono text-white" dir="ltr">{{ $business->economical_code ?: '—' }}</bdi></span>
                    <span>{{ __('Phone') }}: <bdi class="font-mono text-white" dir="ltr">{{ $business->phone_number ?: '—' }}</bdi></span>
                </div>
            </div>

            <div class="flex max-w-full gap-2 overflow-x-auto pb-1" aria-label="{{ __('Fiscal years') }}">
                @foreach ($fiscalYears as $fiscalYear)
                    <div @class([
                        'min-w-24 rounded-xl border px-3 py-2 text-center backdrop-blur',
                        'border-emerald-300/40 bg-emerald-400/15' => !$fiscalYear->closed_at,
                        'border-white/10 bg-white/5' => $fiscalYear->closed_at,
                    ])>
                        <span class="block font-mono text-lg font-black">{{ localizeNumber($fiscalYear->fiscal_year) }}</span>
                        <span class="mt-0.5 block text-[10px] text-slate-300">{{ $fiscalYear->closed_at ? __('Closed') : __('Open') }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-5" aria-label="{{ __('Business usage') }}">
        @foreach ([
            [__('Fiscal years'), $metrics['fiscalYears'], 'text-emerald-600 dark:text-emerald-400'],
            [__('Open fiscal years'), $metrics['openFiscalYears'], 'text-teal-600 dark:text-teal-400'],
            [__('Assigned users'), $metrics['users'], 'text-indigo-600 dark:text-indigo-400'],
            [__('Documents'), $metrics['documents'], 'text-sky-600 dark:text-sky-400'],
            [__('Invoices'), $metrics['invoices'], 'text-amber-600 dark:text-amber-400'],
        ] as [$label, $value, $accent])
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ $label }}</p>
                <strong class="mt-2 block font-mono text-2xl font-black {{ $accent }}">{{ localizeNumber(number_format($value)) }}</strong>
            </article>
        @endforeach
    </section>

    <section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <header class="flex flex-col gap-1 border-b border-slate-200 px-5 py-4 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-bold text-slate-900 dark:text-white">{{ __('Fiscal-year breakdown') }}</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Usage and access for each company record.') }}</p>
            </div>
            <span class="text-xs font-medium text-slate-500">{{ trans_choice(':count record|:count records', $fiscalYears->count(), ['count' => localizeNumber(number_format($fiscalYears->count()))]) }}</span>
        </header>

        <div class="overflow-x-auto">
            <table class="table min-w-[52rem]">
                <thead class="bg-slate-50/80 dark:bg-slate-950/30">
                    <tr>
                        <th>{{ __('Fiscal year') }}</th>
                        <th>{{ __('Users') }}</th>
                        <th>{{ __('Documents') }}</th>
                        <th>{{ __('Invoices') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($fiscalYears as $fiscalYear)
                        <tr class="border-slate-100 transition hover:bg-slate-50/70 dark:border-slate-800 dark:hover:bg-slate-800/30">
                            <td><span class="rounded-lg bg-slate-100 px-2.5 py-1 font-mono font-bold dark:bg-slate-800">{{ localizeNumber($fiscalYear->fiscal_year) }}</span></td>
                            <td>{{ localizeNumber(number_format($fiscalYear->users_count)) }}</td>
                            <td>{{ localizeNumber(number_format($fiscalYear->documents_count)) }}</td>
                            <td>{{ localizeNumber(number_format($fiscalYear->invoices_count)) }}</td>
                            <td>
                                <span @class(['badge gap-1.5', 'badge-success badge-outline' => !$fiscalYear->closed_at, 'badge-ghost' => $fiscalYear->closed_at])>
                                    <span @class(['h-1.5 w-1.5 rounded-full', 'bg-success' => !$fiscalYear->closed_at, 'bg-slate-400' => $fiscalYear->closed_at])></span>
                                    {{ $fiscalYear->closed_at ? __('Closed') : __('Open') }}
                                </span>
                            </td>
                            <td class="text-end">
                                @can('companies.edit')
                                    <a href="{{ route('companies.edit', $fiscalYear) }}" class="btn btn-ghost btn-xs rounded-lg">{{ __('Edit') }}</a>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <header class="flex flex-col gap-1 border-b border-slate-200 px-5 py-4 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-bold text-slate-900 dark:text-white">{{ __('Assigned users') }}</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('People with access to at least one fiscal year for this business.') }}</p>
            </div>
            <span class="text-xs font-medium text-slate-500">{{ trans_choice(':count user|:count users', $users->count(), ['count' => localizeNumber(number_format($users->count()))]) }}</span>
        </header>

        <div class="grid gap-px bg-slate-200 dark:bg-slate-800 sm:grid-cols-2 xl:grid-cols-3">
            @forelse ($users as $user)
                <article class="flex items-start justify-between gap-4 bg-white p-5 dark:bg-slate-900">
                    <div class="flex min-w-0 gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-indigo-100 font-bold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                        <div class="min-w-0">
                            <a href="{{ route('users.show', $user) }}" class="block truncate font-bold text-slate-900 transition hover:text-indigo-700 hover:underline dark:text-white dark:hover:text-indigo-300">{{ $user->name }}</a>
                            <p class="mt-0.5 truncate text-xs text-slate-500">{{ $user->email }}</p>
                            <div class="mt-2 flex flex-wrap gap-1">
                                @forelse ($user->roles as $role)
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $role->name }}</span>
                                @empty
                                    <span class="text-[10px] text-slate-400">{{ __('No roles assigned') }}</span>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    @if (auth()->user()->canImpersonateUser($user))
                        <form action="{{ route('users.impersonate', $user) }}" method="post" onsubmit="return confirm('{{ __('Are you sure you want to impersonate this user?') }}')">
                            @csrf
                            <button type="submit" class="btn btn-ghost btn-xs shrink-0 rounded-lg text-indigo-700 dark:text-indigo-300">{{ __('Impersonate') }}</button>
                        </form>
                    @endif
                </article>
            @empty
                <div class="bg-white px-6 py-14 text-center text-sm text-slate-500 dark:bg-slate-900 dark:text-slate-400 sm:col-span-2 xl:col-span-3">
                    {{ __('No users are assigned to this business.') }}
                </div>
            @endforelse
        </div>
    </section>
</x-platform-layout>
