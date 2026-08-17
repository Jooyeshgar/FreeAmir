<x-super-admin-layout :title="__('Activity log')">
    <section class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-600 dark:text-emerald-400">{{ __('System') }}</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('Activity log') }}</h1>
            <p class="mt-2 max-w-2xl text-sm text-slate-500 dark:text-slate-400">{{ __('Review user actions and model changes across every company.') }}</p>
        </div>

        <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:items-center sm:gap-3">
            <span class="inline-flex min-w-0 items-center justify-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-2 text-center text-xs font-semibold text-slate-700 shadow-sm sm:px-4 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                <span @class(['h-2.5 w-2.5 rounded-full', 'bg-emerald-500 shadow-[0_0_0_4px_rgba(16,185,129,0.14)]' => $recordingEnabled, 'bg-rose-500 shadow-[0_0_0_4px_rgba(244,63,94,0.14)]' => ! $recordingEnabled])></span>
                {{ $recordingStatusLabel }}
            </span>
            <a href="{{ route('management.settings') }}" class="btn btn-outline btn-sm w-full sm:w-auto">
                {{ __('Settings') }}
            </a>
        </div>
    </section>

    <section class="mb-6 grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4" aria-label="{{ __('Activity metrics') }}">
        @foreach ($metricCards as $metric)
            <article class="group relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-3 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-md sm:p-5 dark:border-slate-800 dark:bg-slate-900">
                <span class="absolute inset-x-0 top-0 h-1 {{ $metric['accent'] }}"></span>
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ $metric['label'] }}</p>
                        <strong class="mt-2 block text-2xl font-black tracking-tight text-slate-900 sm:text-3xl dark:text-white">{{ $metric['value'] }}</strong>
                    </div>
                    <span class="hidden h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-500 transition group-hover:bg-emerald-50 group-hover:text-emerald-600 sm:flex dark:bg-slate-800 dark:text-slate-400 dark:group-hover:bg-emerald-950/40 dark:group-hover:text-emerald-300">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="h-5 w-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $metric['icon'] }}" /></svg>
                    </span>
                </div>
            </article>
        @endforeach
    </section>

    <section class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm sm:rounded-3xl dark:border-slate-800 dark:bg-slate-900">
        <header class="flex flex-col justify-between gap-3 border-b border-slate-200 bg-slate-50/70 px-4 py-4 sm:flex-row sm:items-center sm:px-5 dark:border-slate-800 dark:bg-slate-950/30">
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="font-bold text-slate-900 dark:text-white">{{ __('Filter activity') }}</h2>
                    @if ($activeFilterCount > 0)
                        <span class="inline-flex min-w-6 items-center justify-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300">{{ $activeFilterCountLabel }}</span>
                    @endif
                </div>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Narrow activity by user, company, model, action, or date.') }}</p>
            </div>
            @if ($activeFilterCount > 0)
                <a href="{{ route('management.activity-logs.index') }}" class="btn btn-ghost btn-sm w-full sm:w-auto">{{ __('Clear filters') }}</a>
            @endif
        </header>

        <form action="{{ route('management.activity-logs.index') }}" method="GET" class="grid gap-x-4 gap-y-4 p-4 sm:grid-cols-2 sm:p-5 xl:grid-cols-5" x-data>
            <label class="form-control sm:col-span-2 xl:col-span-2">
                <span class="mb-2 block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('Search activities') }}</span>
                <div class="relative">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="pointer-events-none absolute end-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m2.1-5.4a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z" /></svg>
                    <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" class="input input-bordered w-full" placeholder="{{ __('User, model, route, or description') }}">
                </div>
            </label>

            <label class="form-control">
                <span class="mb-2 block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('Action') }}</span>
                <select name="action" class="select select-bordered w-full">
                    <option value="">{{ __('All actions') }}</option>
                    @foreach ($actionOptions as $value => $action)
                        <option value="{{ $value }}" @selected(($filters['action'] ?? '') === $value)>{{ $action['label'] }}</option>
                    @endforeach
                </select>
            </label>

            <label class="form-control">
                <span class="mb-2 block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('Performed by') }}</span>
                <select name="user_id" class="select select-bordered w-full">
                    <option value="">{{ __('All users') }}</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="form-control">
                <span class="mb-2 block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('Company') }}</span>
                <select name="company_id" class="select select-bordered w-full">
                    <option value="">{{ __('All companies') }}</option>
                    @foreach ($companyOptions as $company)
                        <option value="{{ $company['value'] }}" @selected((string) ($filters['company_id'] ?? '') === (string) $company['value'])>{{ $company['label'] }}</option>
                    @endforeach
                </select>
            </label>

            <label class="form-control">
                <span class="mb-2 block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('Affected model') }}</span>
                <select name="model_type" class="select select-bordered w-full">
                    <option value="">{{ __('All models') }}</option>
                    @foreach ($modelOptions as $model)
                        <option value="{{ $model['value'] }}" @selected(($filters['model_type'] ?? '') === $model['value'])>{{ $model['label'] }}</option>
                    @endforeach
                </select>
            </label>

            <label class="form-control">
                <span class="mb-2 block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('Model number or ID') }}</span>
                <input type="search" name="model_identifier" value="{{ $filters['model_identifier'] ?? '' }}" class="input input-bordered w-full" placeholder="{{ __('Number or ID') }}" dir="ltr">
            </label>

            <div class="form-control">
                <span class="mb-2 block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('From date') }}</span>
                <x-date-picker name="date_from" id="date_from" title="" :value="$dateFromValue" :placeholder="__('Select date')" x-bind:readonly="true" />
            </div>

            <div class="form-control">
                <span class="mb-2 block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('To date') }}</span>
                <x-date-picker name="date_to" id="date_to" title="" :value="$dateToValue" :placeholder="__('Select date')" x-bind:readonly="true" />
            </div>

            <div class="grid grid-cols-2 gap-2 sm:col-span-2 sm:flex sm:flex-wrap sm:items-center sm:justify-end xl:col-span-5">
                <button type="submit" class="btn btn-primary w-full sm:w-auto sm:min-w-36">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.5h16.5l-6.375 7.084v5.166l-3.75 2.75v-7.916L3.75 4.5Z" /></svg>
                    {{ __('Apply filters') }}
                </button>
                @if ($activeFilterCount > 0)
                    <a href="{{ route('management.activity-logs.index') }}" class="btn btn-ghost w-full sm:w-auto">{{ __('Reset') }}</a>
                @endif
            </div>
        </form>
    </section>

    <section class="space-y-3 sm:space-y-4" aria-label="{{ __('Activity') }}">
        @forelse ($activities as $activity)
            <article x-data="{ detailsOpen: false, requestInputOpen: false }" class="group rounded-2xl border border-slate-200 bg-white p-3 shadow-sm transition duration-200 hover:border-slate-300 hover:shadow-md sm:p-4 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                    <div class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1">
                        @if ($activity['contextUrl'])
                            <a href="{{ $activity['contextUrl'] }}" class="max-w-full truncate font-mono text-xs font-semibold text-slate-600 hover:underline sm:max-w-80 dark:text-slate-300" dir="ltr">{{ $activity['contextLabel'] }}</a>
                        @else
                            <bdi class="max-w-full truncate font-mono text-xs font-semibold text-slate-600 sm:max-w-80 dark:text-slate-300" dir="ltr">{{ $activity['contextLabel'] }}</bdi>
                        @endif
                        @foreach ($activity['modelContextLinks'] as $modelContextLink)
                            @if ($modelContextLink['label'] !== $activity['contextLabel'])
                                @if ($modelContextLink['url'])
                                    <a href="{{ $modelContextLink['url'] }}" class="max-w-full truncate font-mono text-xs font-semibold text-sky-700 hover:underline sm:max-w-64 dark:text-sky-300" dir="ltr">{{ $modelContextLink['label'] }}</a>
                                @else
                                    <bdi class="max-w-full truncate font-mono text-xs font-semibold text-sky-700 sm:max-w-64 dark:text-sky-300" dir="ltr">{{ $modelContextLink['label'] }}</bdi>
                                @endif
                            @endif
                        @endforeach
                        @if ($activity['userUrl'])
                            <a href="{{ $activity['userUrl'] }}" class="shrink-0 font-semibold text-violet-700 transition hover:underline dark:text-violet-400">{{ $activity['userName'] }}</a>
                        @else
                            <span class="shrink-0 font-semibold text-slate-800 dark:text-slate-100">{{ $activity['userName'] }}</span>
                        @endif
                    </div>
                    <time datetime="{{ $activity['createdAt'] }}" class="self-end text-[11px] text-slate-400 sm:shrink-0 sm:self-auto" dir="ltr">{{ $activity['createdAtLabel'] }}</time>
                </div>

                <div class="mt-3 flex flex-wrap items-center justify-end gap-1.5 border-t border-slate-100 pt-3 sm:gap-2 dark:border-slate-800">
                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-bold ring-1 ring-inset {{ $activity['actionStyle'] }}">{{ $activity['actionLabel'] }}</span>
                    @if ($activity['requestMethod'])
                        <span class="inline-flex items-center rounded-full bg-violet-50 px-2.5 py-1 font-mono text-[11px] font-bold text-violet-700 ring-1 ring-inset ring-violet-600/20 dark:bg-violet-950/40 dark:text-violet-300" dir="ltr">{{ $activity['requestMethod'] }}</span>
                    @endif
                    @if ($activity['companyLabel'])
                        <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-700 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-950/40 dark:text-amber-300">{{ $activity['companyLabel'] }}</span>
                    @endif
                    @if ($activity['impersonatedUserName'])
                        <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-800 dark:bg-amber-950/30 dark:text-amber-200">{{ __('Impersonated account') }}: {{ $activity['impersonatedUserName'] }}</span>
                    @endif
                    @if ($activity['hasDetails'])
                        <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20 transition hover:bg-emerald-100 sm:h-7 sm:w-7 dark:bg-emerald-950/40 dark:text-emerald-300 dark:hover:bg-emerald-950/70" @click="detailsOpen = ! detailsOpen" :aria-expanded="detailsOpen" aria-controls="activity-details-{{ $activity['id'] }}" aria-label="{{ __('Details') }}" title="{{ __('Details') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                        </button>
                    @endif
                </div>

                @if ($activity['hasDetails'])
                    <div id="activity-details-{{ $activity['id'] }}" x-cloak x-show="detailsOpen" class="mt-4 border-t border-slate-100 pt-4 dark:border-slate-800">
                        @if ($activity['requestContext'])
                            <dl class="grid gap-2 text-xs sm:grid-cols-2 sm:gap-3 xl:grid-cols-5">
                                @foreach ($activity['requestContext'] as $context)
                                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-950/40">
                                        <dt class="font-semibold text-slate-500">{{ $context['label'] }}</dt>
                                        <dd class="mt-1 break-all font-mono text-slate-800 dark:text-slate-200" dir="ltr">
                                            @if ($context['url'] ?? null)
                                                <a href="{{ $context['url'] }}" class="text-sky-700 hover:underline dark:text-sky-300">{{ $context['value'] }}</a>
                                            @else
                                                {{ $context['value'] }}
                                            @endif
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>
                            @if ($activity['requestInput'])
                                <button type="button" class="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-2.5 py-1.5 text-[11px] font-semibold text-slate-600 ring-1 ring-inset ring-slate-200 transition hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700 dark:hover:bg-slate-700" @click="requestInputOpen = ! requestInputOpen" :aria-expanded="requestInputOpen" aria-controls="request-input-{{ $activity['id'] }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-3.5 w-3.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3.75h9m-9 3.75h5.25M5.25 3.75h13.5a1.5 1.5 0 0 1 1.5 1.5v13.5a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5V5.25a1.5 1.5 0 0 1 1.5-1.5Z" /></svg>
                                    {{ __('Request details') }}
                                </button>
                                <pre id="request-input-{{ $activity['id'] }}" x-cloak x-show="requestInputOpen" class="mt-2 max-h-72 max-w-full overflow-auto rounded-xl bg-slate-950 p-3 text-[11px] leading-5 text-slate-100 sm:p-4 sm:text-xs sm:leading-6" dir="ltr">{{ $activity['requestInput'] }}</pre>
                            @endif
                        @endif
                        @if ($activity['changes']->isNotEmpty())
                            <div @class(['space-y-3', 'mt-3' => $activity['requestContext']])>
                                @foreach ($activity['changes']->groupBy('model') as $affectedModel => $modelChanges)
                                    <section x-data="{ modelOpen: false }" class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800" data-affected-model="{{ $affectedModel }}">
                                        <header class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-800 dark:bg-slate-950/40">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">{{ __('Affected model') }}</span>
                                                @if ($modelChanges->first()['url'] ?? null)
                                                    <a href="{{ $modelChanges->first()['url'] }}" class="font-mono text-xs font-bold text-sky-700 hover:underline dark:text-sky-300" dir="ltr">{{ $affectedModel }}</a>
                                                @else
                                                    <bdi class="font-mono text-xs font-bold text-sky-700 dark:text-sky-300" dir="ltr">{{ $affectedModel }}</bdi>
                                                @endif
                                            </div>
                                            <button type="button" class="inline-flex items-center gap-1 rounded-lg bg-white px-2.5 py-1.5 text-[11px] font-semibold text-slate-600 ring-1 ring-inset ring-slate-200 transition hover:bg-slate-100 dark:bg-slate-900 dark:text-slate-300 dark:ring-slate-700 dark:hover:bg-slate-800" @click="modelOpen = ! modelOpen" :aria-expanded="modelOpen" aria-label="{{ __('Details') }}">
                                                <span x-text="modelOpen ? '{{ __('Close') }}' : '{{ __('Details') }}'"></span>
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-3.5 w-3.5 transition-transform" :class="modelOpen ? 'rotate-180' : ''" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg>
                                            </button>
                                        </header>

                                        <div x-cloak x-show="modelOpen" class="space-y-2 sm:hidden p-3">
                                            @foreach ($modelChanges as $change)
                                                <div @class(['rounded-lg border border-slate-200 p-3 text-xs dark:border-slate-800', 'bg-amber-50/70 font-bold dark:bg-amber-950/20' => $change['old'] !== $change['new']])>
                                                    <p class="mb-2 font-mono font-bold text-slate-700 dark:text-slate-200" dir="ltr">{{ $change['field'] }}</p>
                                                    <dl class="grid gap-2">
                                                        <div @class(['rounded-lg p-2', 'bg-rose-50 dark:bg-rose-950/30' => $change['old'] !== $change['new']])><dt class="text-[11px] font-semibold text-slate-400">{{ __('Previous value') }}</dt><dd><pre @class(['mt-1 whitespace-pre-wrap break-all', 'font-bold text-rose-700 dark:text-rose-300' => $change['old'] !== $change['new']]) dir="ltr">{{ $change['old'] }}</pre></dd></div>
                                                        <div @class(['rounded-lg p-2', 'bg-emerald-50 dark:bg-emerald-950/30' => $change['old'] !== $change['new']])><dt class="text-[11px] font-semibold text-slate-400">{{ __('New value') }}</dt><dd><pre @class(['mt-1 whitespace-pre-wrap break-all', 'font-bold text-emerald-700 dark:text-emerald-300' => $change['old'] !== $change['new']]) dir="ltr">{{ $change['new'] }}</pre></dd></div>
                                                    </dl>
                                                </div>
                                            @endforeach
                                        </div>

                                        <div x-cloak x-show="modelOpen" class="hidden overflow-x-auto sm:block">
                                            <table class="table table-sm min-w-[32rem]">
                                                <thead><tr><th>{{ __('Field') }}</th><th>{{ __('Previous value') }}</th><th>{{ __('New value') }}</th></tr></thead>
                                                <tbody>
                                                    @foreach ($modelChanges as $change)
                                                        <tr @class(['bg-amber-50/70 font-bold dark:bg-amber-950/20' => $change['old'] !== $change['new']])>
                                                            <td @class(['font-mono text-xs', 'font-bold' => $change['old'] !== $change['new']]) dir="ltr">{{ $change['field'] }}</td>
                                                            <td><pre @class(['whitespace-pre-wrap break-all text-xs', 'font-bold text-rose-700 dark:text-rose-300' => $change['old'] !== $change['new']]) dir="ltr">{{ $change['old'] }}</pre></td>
                                                            <td><pre @class(['whitespace-pre-wrap break-all text-xs', 'font-bold text-emerald-700 dark:text-emerald-300' => $change['old'] !== $change['new']]) dir="ltr">{{ $change['new'] }}</pre></td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </section>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            </article>
        @empty
            <div class="rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-20 text-center dark:border-slate-700 dark:bg-slate-900">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-slate-800">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="h-7 w-7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                </span>
                <h2 class="mt-4 font-bold text-slate-900 dark:text-white">{{ __('No activity found.') }}</h2>
                <p class="mt-2 text-sm text-slate-500">{{ $activeFilterCount > 0 ? __('Try changing or clearing your filters.') : __('New user actions and model changes will appear here.') }}</p>
                @if ($activeFilterCount > 0)
                    <a href="{{ route('management.activity-logs.index') }}" class="btn btn-outline btn-sm mt-5">{{ __('Clear filters') }}</a>
                @endif
            </div>
        @endforelse
    </section>

    @if ($activities->hasPages())
        <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-slate-900">{{ $activities->links() }}</div>
    @endif
</x-super-admin-layout>
