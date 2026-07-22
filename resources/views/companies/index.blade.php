<x-platform-layout :title="__('Companies')">
    <x-show-message-bags />

    <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-600 dark:text-emerald-400">{{ __('Organization') }}</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight">{{ __('Companies and fiscal years') }}</h2>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ __('Manage every company record and its fiscal-year access.') }}</p>
        </div>
        @cannot('access-super-admin-panel')
            @can('companies.create')
                <a href="{{ route('companies.create') }}" class="btn btn-primary gap-2 rounded-xl shadow-lg shadow-primary/15">
                    <span class="text-lg leading-none">+</span>{{ __('Create Company') }}
                </a>
            @endcan
        @endcannot
    </div>

    <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-col justify-between gap-2 border-b border-slate-200/80 p-3 dark:border-slate-800 sm:flex-row sm:items-center">
            <form action="{{ route('companies.index') }}" method="GET" class="flex w-full min-w-0 flex-nowrap items-center gap-1.5 overflow-x-auto pb-1 sm:flex-1">
                <label class="input input-bordered flex h-9 w-[19.5rem] max-w-full shrink-0 items-center gap-1.5 rounded-lg bg-slate-50 text-sm dark:bg-slate-950/50 sm:w-[21rem]">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-4.35-4.35M19 11a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z" /></svg>
                    <input type="search" name="search" value="{{ request('search') }}" class="grow" placeholder="{{ __('Search companies') }}">
                </label>
                <select name="status" class="select select-bordered h-9 min-h-9 w-36 shrink-0 rounded-lg bg-slate-50 text-sm dark:bg-slate-950/50" aria-label="{{ __('Status') }}">
                    <option value="">{{ __('All statuses') }}</option>
                    <option value="open" @selected(request('status') === 'open')>{{ __('Open') }}</option>
                    <option value="closed" @selected(request('status') === 'closed')>{{ __('Closed') }}</option>
                </select>
                <button class="btn btn-neutral btn-sm h-9 min-h-9 shrink-0 rounded-lg px-3" type="submit">{{ __('Filter') }}</button>
                @if (request()->hasAny(['search', 'status']))
                    <a href="{{ route('companies.index') }}" class="btn btn-ghost btn-sm h-9 min-h-9 shrink-0 rounded-lg px-3">{{ __('Clear') }}</a>
                @endif
            </form>
            <span class="whitespace-nowrap text-xs font-medium text-slate-500 dark:text-slate-400">
                {{ trans_choice(':count record|:count records', $companies->total(), ['count' => localizeNumber(number_format($companies->total()))]) }}
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="table table-lg">
                <thead class="bg-slate-50/80 dark:bg-slate-950/30">
                    <tr>
                        <th>{{ __('Company') }}</th>
                        <th>{{ __('Fiscal year') }}</th>
                        <th>{{ __('Users') }}</th>
                        <th>{{ __('Identifiers') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($companies as $company)
                        <tr class="border-slate-100 transition-colors hover:bg-slate-50/70 dark:border-slate-800 dark:hover:bg-slate-800/30">
                            <td>
                                <div class="flex min-w-48 items-center gap-3">
                                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-linear-to-br from-emerald-100 to-teal-100 font-bold text-emerald-700 dark:from-emerald-950 dark:to-teal-950 dark:text-emerald-300">
                                        {{ mb_strtoupper(mb_substr($company->name, 0, 1)) }}
                                    </span>
                                    <div>
                                        <p class="font-semibold text-slate-900 dark:text-white">{{ $company->name }}</p>
                                    <p class="mt-0.5 max-w-56 truncate text-xs text-slate-500">{{ $company->address ? localizeNumber($company->address) : __('No address') }}</p>
                                    </div>
                                </div>
                            </td>
                            <td><span class="rounded-lg bg-slate-100 px-2.5 py-1 font-mono text-sm dark:bg-slate-800">{{ localizeNumber($company->fiscal_year) }}</span></td>
                            <td><span class="badge badge-ghost">{{ localizeNumber(number_format($company->users_count)) }}</span></td>
                            <td>
                                <div class="space-y-1 text-xs">
                                    <p><span class="text-slate-400">{{ __('National Code') }}:</span> {{ $company->national_code ? localizeNumber($company->national_code) : '—' }}</p>
                                    <p><span class="text-slate-400">{{ __('Economical Code') }}:</span> {{ $company->economical_code ? localizeNumber($company->economical_code) : '—' }}</p>
                                </div>
                            </td>
                            <td>
                                <span @class(['badge gap-1.5', 'badge-success badge-outline' => ! $company->closed_at, 'badge-ghost' => $company->closed_at])>
                                    <span @class(['h-1.5 w-1.5 rounded-full', 'bg-success' => ! $company->closed_at, 'bg-slate-400' => $company->closed_at])></span>
                                    {{ $company->closed_at ? __('Closed') : __('Open') }}
                                </span>
                            </td>
                            <td>
                                <div class="flex justify-end gap-1">
                                    @can('companies.edit')
                                        <a href="{{ route('companies.edit', $company) }}" class="btn btn-ghost btn-sm rounded-lg">{{ __('Edit') }}</a>
                                    @endcan
                                    @cannot('access-super-admin-panel')
                                        @can('companies.close-fiscal-year')
                                            <a href="{{ route('companies.closing-wizard', $company) }}" @class(['btn btn-ghost btn-sm rounded-lg', 'btn-disabled' => $company->closed_at])>{{ __('Close Fiscal Year') }}</a>
                                        @endcan
                                    @endcannot
                                    @can('companies.destroy')
                                        <form action="{{ route('companies.destroy', $company) }}" method="POST" onsubmit="return confirm('{{ __('Are you sure you want to delete this company?') }}');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-ghost btn-sm rounded-lg text-error">{{ __('Delete') }}</button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-20 text-center"><p class="font-semibold text-slate-600 dark:text-slate-300">{{ __('No companies found.') }}</p>@cannot('access-super-admin-panel')<p class="mt-1 text-sm text-slate-400">{{ __('Try changing your filters or create a company.') }}</p>@endcannot</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($companies->hasPages())
            <div class="border-t border-slate-200 p-4 dark:border-slate-800">{{ $companies->links() }}</div>
        @endif
    </section>
</x-platform-layout>
