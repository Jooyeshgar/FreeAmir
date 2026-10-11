<x-platform-layout :title="__('Fiscal years')">
    <x-show-message-bags />

    <div class="mb-6 px-2 flex flex-wrap items-center justify-between gap-4 mt-2">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-600 dark:text-emerald-400">{{ __('Organization') }}</p>
            <div class="mt-1 flex items-center gap-1">
                <h1 class="text-2xl font-bold tracking-tight">{{ __('Fiscal years') }}</h1>
                <x-user-guide-link source="management/system/companies/fiscal-year.md" />
            </div>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ __('Companies and fiscal years') }}</p>
        </div>
        @can('fiscal-years.create')
            <a href="{{ route('fiscal-years.create') }}" class="btn btn-primary gap-2 rounded-xl shadow-lg shadow-primary/15">
                <span class="text-lg leading-none">+</span>{{ __('Create Fiscal Year') }}
            </a>
        @endcan
    </div>

    <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-col justify-between gap-2 border-b border-slate-200/80 p-3 dark:border-slate-800 sm:flex-row sm:items-center">
            <form action="{{ route('fiscal-years.index') }}" method="GET" class="flex w-full min-w-0 flex-nowrap items-center gap-1.5 overflow-x-auto pb-1 sm:flex-1">
                <label class="input input-bordered flex h-9 w-[19.5rem] max-w-full shrink-0 items-center gap-1.5 rounded-lg bg-slate-50 text-sm dark:bg-slate-950/50 sm:w-[21rem]">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-4.35-4.35M19 11a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z" />
                    </svg>
                    <input type="search" name="search" value="{{ request('search') }}" class="grow" placeholder="{{ __('Search companies') }}">
                </label>
                <select name="status" class="select select-bordered h-9 min-h-9 w-36 shrink-0 rounded-lg bg-slate-50 text-sm dark:bg-slate-950/50" aria-label="{{ __('Status') }}">
                    <option value="">{{ __('All statuses') }}</option>
                    <option value="open" @selected(request('status') === 'open')>{{ __('Open') }}</option>
                    <option value="closed" @selected(request('status') === 'closed')>{{ __('Closed') }}</option>
                </select>
                <button type="submit" class="btn btn-neutral btn-sm h-9 min-h-9 shrink-0 rounded-lg px-3">{{ __('Filter') }}</button>
                @if (request()->hasAny(['search', 'status']))
                    <a href="{{ route('fiscal-years.index') }}" class="btn btn-ghost btn-sm h-9 min-h-9 shrink-0 rounded-lg px-3">{{ __('Clear') }}</a>
                @endif
            </form>
            <span class="whitespace-nowrap text-xs font-medium text-slate-500 dark:text-slate-400">
                {{ trans_choice(':count record|:count records', $years->total(), ['count' => localizeNumber(number_format($years->total()))]) }}
            </span>
        </div>
        <div class="overflow-x-auto">
            <table class="table table-lg">
                <thead class="bg-slate-50/80 dark:bg-slate-950/30">
                    <tr>
                        <th>{{ __('Company') }}</th>
                        <th>{{ __('Fiscal year') }}</th>
                        <th>{{ __('Users') }}</th>
                        <th>{{ __('Documents') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($years as $year)
                        <tr class="border-slate-100 transition-colors hover:bg-slate-50/70 dark:border-slate-800 dark:hover:bg-slate-800/30">
                            <td>
                                <div class="flex min-w-48 items-center gap-3">
                                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-linear-to-br from-emerald-100 to-teal-100 font-bold text-emerald-700 dark:from-emerald-950 dark:to-teal-950 dark:text-emerald-300">
                                        {{ mb_strtoupper(mb_substr($year->company->name, 0, 1)) }}
                                    </span>
                                    <div>
                                        <span class="font-semibold text-slate-900 dark:text-white">{{ $year->company->name }}</span>
                                        <p class="mt-0.5 max-w-56 truncate text-xs text-slate-500">
                                            {{ $year->company->address ? localizeNumber($year->company->address) : __('No address') }}
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @can('fiscal-years.show')
                                    <a href="{{ route('fiscal-years.show', $year) }}" class="rounded-lg bg-slate-100 px-2.5 py-1 font-mono text-sm hover:text-emerald-700 hover:underline dark:bg-slate-800 dark:hover:text-emerald-300">{{ localizeNumber($year->year) }}</a>
                                @else
                                    <span class="rounded-lg bg-slate-100 px-2.5 py-1 font-mono text-sm dark:bg-slate-800">{{ localizeNumber($year->year) }}</span>
                                @endcan
                            </td>
                            <td><span class="badge badge-ghost">{{ localizeNumber(number_format($year->users_count)) }}</span></td>
                            <td><span class="badge badge-ghost">{{ localizeNumber(number_format($year->documents_count)) }}</span></td>
                            <td>
                                <span @class(['badge gap-1.5', 'badge-success badge-outline' => !$year->closed_at, 'badge-ghost' => $year->closed_at])>
                                    <span @class(['h-1.5 w-1.5 rounded-full', 'bg-success' => !$year->closed_at, 'bg-slate-400' => $year->closed_at])></span>
                                    {{ $year->closed_at ? __('Closed') : __('Open') }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="flex justify-end gap-1">
                                    @can('fiscal-years.edit')
                                        <a href="{{ route('fiscal-years.edit', $year) }}" class="btn btn-ghost btn-sm rounded-lg">{{ __('Edit') }}</a>
                                    @endcan
                                    @can('fiscal-years.destroy')
                                        <form action="{{ route('fiscal-years.destroy', $year) }}" method="POST" onsubmit="return confirm('{{ __('Are you sure you want to delete this fiscal year?') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-ghost btn-sm rounded-lg text-error">{{ __('Delete') }}</button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-20 text-center font-semibold text-slate-600 dark:text-slate-300">{{ __('No fiscal years found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($years->hasPages())
            <div class="border-t border-slate-200 p-4 dark:border-slate-800">{{ $years->links() }}</div>
        @endif
    </section>
</x-platform-layout>
