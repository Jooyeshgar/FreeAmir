<x-super-admin-layout :title="__('Activity log')">
    <section class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-600 dark:text-emerald-400">{{ __('System') }}</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('Activity log') }}</h1>
            <p class="mt-2 max-w-2xl text-sm text-slate-500 dark:text-slate-400">{{ __('Review user actions and model changes across every company.') }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                <span @class(['h-2.5 w-2.5 rounded-full', 'bg-emerald-500 shadow-[0_0_0_4px_rgba(16,185,129,0.14)]' => $recordingEnabled, 'bg-rose-500 shadow-[0_0_0_4px_rgba(244,63,94,0.14)]' => ! $recordingEnabled])></span>
                {{ $recordingStatusLabel }}
            </span>
            <a href="{{ route('management.settings') }}" class="btn btn-outline btn-sm">
                {{ __('Settings') }}
            </a>
        </div>
    </section>

    <section class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="{{ __('Activity metrics') }}">
        @foreach ($metricCards as $metric)
            <article class="group relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                <span class="absolute inset-x-0 top-0 h-1 {{ $metric['accent'] }}"></span>
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ $metric['label'] }}</p>
                        <strong class="mt-2 block text-3xl font-black tracking-tight text-slate-900 dark:text-white">{{ $metric['value'] }}</strong>
                    </div>
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-500 transition group-hover:bg-emerald-50 group-hover:text-emerald-600 dark:bg-slate-800 dark:text-slate-400 dark:group-hover:bg-emerald-950/40 dark:group-hover:text-emerald-300">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="h-5 w-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $metric['icon'] }}" /></svg>
                    </span>
                </div>
            </article>
        @endforeach
    </section>

    <section class="mb-6 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <header class="flex flex-col justify-between gap-3 border-b border-slate-200 bg-slate-50/70 px-5 py-4 sm:flex-row sm:items-center dark:border-slate-800 dark:bg-slate-950/30">
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
                <a href="{{ route('management.activity-logs.index') }}" class="btn btn-ghost btn-sm">{{ __('Clear filters') }}</a>
            @endif
        </header>

        <form action="{{ route('management.activity-logs.index') }}" method="GET" class="grid gap-x-4 gap-y-5 p-5 lg:grid-cols-2 xl:grid-cols-4">
            <label class="form-control xl:col-span-2">
                <span class="mb-2 block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('Search activities') }}</span>
                <div class="relative">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m2.1-5.4a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z" /></svg>
                    <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" class="input input-bordered w-full ps-10" placeholder="{{ __('User, model, route, or description') }}">
                </div>
            </label>

            <label class="form-control">
                <span class="mb-2 flex items-center gap-1.5 text-xs font-semibold text-slate-600 dark:text-slate-300">
                    {{ __('Activity source') }}
                    <span
                        class="tooltip tooltip-bottom inline-flex cursor-help text-slate-400 before:max-w-72 before:whitespace-normal hover:text-emerald-600 focus:text-emerald-600 focus:outline-none dark:hover:text-emerald-300 dark:focus:text-emerald-300"
                        data-tip="{{ __('Model changes track created, updated, and deleted records. User actions track successful write requests.') }}"
                        tabindex="0"
                        aria-label="{{ __('Model changes track created, updated, and deleted records. User actions track successful write requests.') }}"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 9.879a3 3 0 1 1 4.242 4.242c-.879.879-2.121 1.371-2.121 2.629M12 19.5h.008v.008H12V19.5ZM21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </span>
                </span>
                <select name="source" class="select select-bordered w-full">
                    <option value="">{{ __('All sources') }}</option>
                    <option value="model" @selected(($filters['source'] ?? '') === 'model')>{{ __('Model changes') }}</option>
                    <option value="request" @selected(($filters['source'] ?? '') === 'request')>{{ __('User actions') }}</option>
                </select>
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
                        <option value="{{ $user->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $user->id)>{{ $user->name }} - {{ $user->email }}</option>
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

            <div class="form-control">
                <span class="mb-2 block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('From date') }}</span>
                <x-date-picker name="date_from" id="date_from" title="" :value="$dateFromValue" :placeholder="__('Select date')" />
            </div>

            <div class="form-control">
                <span class="mb-2 block text-xs font-semibold text-slate-600 dark:text-slate-300">{{ __('To date') }}</span>
                <x-date-picker name="date_to" id="date_to" title="" :value="$dateToValue" :placeholder="__('Select date')" />
            </div>

            <div class="flex flex-wrap items-center justify-start gap-2 rtl:justify-end lg:col-span-2 xl:col-span-4">
                <button type="submit" class="btn btn-primary min-w-36">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.5h16.5l-6.375 7.084v5.166l-3.75 2.75v-7.916L3.75 4.5Z" /></svg>
                    {{ __('Apply filters') }}
                </button>
                @if ($activeFilterCount > 0)
                    <a href="{{ route('management.activity-logs.index') }}" class="btn btn-ghost">{{ __('Reset') }}</a>
                @endif
            </div>
        </form>
    </section>

    <section class="space-y-4" aria-label="{{ __('Activity') }}">
        @forelse ($activities as $activity)
            <article class="group overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm transition duration-200 hover:border-slate-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700">
                <div class="flex flex-col gap-5 p-5 sm:p-6 lg:flex-row lg:items-start lg:justify-between">
                    <div class="flex min-w-0 gap-4">
                        <span @class([
                            'flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl text-sm font-black ring-1 ring-inset',
                            'bg-violet-50 text-violet-700 ring-violet-600/10 dark:bg-violet-950/40 dark:text-violet-300' => $activity['isRequest'],
                            'bg-sky-50 text-sky-700 ring-sky-600/10 dark:bg-sky-950/40 dark:text-sky-300' => ! $activity['isRequest'],
                        ])>
                            {{ $activity['userInitial'] }}
                        </span>

                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-bold ring-1 ring-inset {{ $activity['actionStyle'] }}">{{ $activity['actionLabel'] }}</span>
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                    {{ $activity['sourceLabel'] }}
                                </span>
                                @if ($activity['companyLabel'])
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">{{ $activity['companyLabel'] }}</span>
                                @endif
                            </div>

                            <h2 class="mt-3 break-words text-base font-bold text-slate-900 dark:text-white">
                                @if ($activity['isRequest'])
                                    <span dir="ltr">{{ $activity['title'] }}</span>
                                @else
                                    {{ $activity['title'] }}
                                    <span class="font-medium text-slate-500 dark:text-slate-400">- {{ $activity['titleDetail'] }}</span>
                                @endif
                            </h2>

                            <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                                <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $activity['userName'] }}</span>
                                @if ($activity['userEmail'])
                                    <span dir="ltr">{{ $activity['userEmail'] }}</span>
                                @endif
                                @if ($activity['route'])
                                    <span class="font-mono" dir="ltr">{{ $activity['route'] }}</span>
                                @elseif ($activity['modelId'])
                                    <span>{{ __('Model ID') }}: <bdi class="font-mono">{{ $activity['modelId'] }}</bdi></span>
                                @endif
                                @if ($activity['ipAddress'])
                                    <span dir="ltr">{{ $activity['ipAddress'] }}</span>
                                @endif
                            </div>

                            @if ($activity['impersonatedUserName'])
                                <div class="mt-3 inline-flex items-center gap-2 rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-800 ring-1 ring-inset ring-amber-600/15 dark:bg-amber-950/30 dark:text-amber-200">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.118a7.5 7.5 0 0 1 15 0A17.93 17.93 0 0 1 12 21.75a17.93 17.93 0 0 1-7.5-1.632Z" /></svg>
                                    {{ __('Impersonated account') }}: <strong>{{ $activity['impersonatedUserName'] }}</strong>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center justify-between gap-4 lg:flex-col lg:items-end">
                        <time datetime="{{ $activity['createdAt'] }}" class="text-xs font-medium text-slate-500" dir="ltr">{{ $activity['createdAtLabel'] }}</time>
                        <span class="text-[11px] text-slate-400" dir="ltr">#{{ $activity['id'] }}</span>
                    </div>
                </div>

                @if ($activity['hasDetails'])
                    <details class="group/details border-t border-slate-100 dark:border-slate-800">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-3.5 text-xs font-bold text-emerald-700 transition hover:bg-emerald-50/50 dark:text-emerald-300 dark:hover:bg-emerald-950/20 sm:px-6">
                            <span>{{ $activity['detailsLabel'] }}</span>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4 transition group-open/details:rotate-180" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                        </summary>

                        <div class="border-t border-slate-100 bg-slate-50/70 p-5 dark:border-slate-800 dark:bg-slate-950/30 sm:p-6">
                            @if ($activity['isRequest'])
                                <dl class="grid gap-3 text-xs md:grid-cols-2 xl:grid-cols-4">
                                    @foreach ($activity['requestContext'] as $context)
                                        <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
                                            <dt class="font-semibold text-slate-500">{{ $context['label'] }}</dt>
                                            <dd class="mt-1 break-all font-mono text-slate-800 dark:text-slate-200" dir="ltr">{{ $context['value'] }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
                                @if ($activity['requestInput'])
                                    <div class="mt-4">
                                        <p class="mb-2 text-xs font-bold text-slate-600 dark:text-slate-300">{{ __('Sanitized request data') }}</p>
                                        <pre class="max-h-72 overflow-auto rounded-2xl bg-slate-950 p-4 text-xs leading-6 text-slate-100 ring-1 ring-white/10" dir="ltr">{{ $activity['requestInput'] }}</pre>
                                    </div>
                                @endif
                            @else
                                <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                                    <table class="table table-sm">
                                        <thead class="bg-slate-100/80 text-xs dark:bg-slate-800/70"><tr><th>{{ __('Field') }}</th><th>{{ __('Previous value') }}</th><th>{{ __('New value') }}</th></tr></thead>
                                        <tbody>
                                            @foreach ($activity['changes'] as $change)
                                                <tr>
                                                    <td class="font-mono text-xs font-semibold" dir="ltr">{{ $change['field'] }}</td>
                                                    <td><pre class="max-w-xl whitespace-pre-wrap break-all text-xs" dir="ltr">{{ $change['old'] }}</pre></td>
                                                    <td><pre class="max-w-xl whitespace-pre-wrap break-all text-xs" dir="ltr">{{ $change['new'] }}</pre></td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </details>
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
