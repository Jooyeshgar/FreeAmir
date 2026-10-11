<x-platform-layout :title="__('Fiscal year') . ' ' . localizeNumber($fiscalYear->year)">
    <x-show-message-bags />

    <div class="mx-3 space-y-5 sm:mx-5">
        @can('fiscal-years.index')
            <a href="{{ route('fiscal-years.index') }}" class="inline-flex items-center gap-2 text-sm font-medium text-slate-500 transition hover:text-emerald-700 dark:text-slate-400 dark:hover:text-emerald-300">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 19-7-7 7-7" /></svg>
                {{ __('Fiscal years') }}
            </a>
        @endcan

        <section class="relative overflow-hidden rounded-3xl border border-emerald-200/70 bg-slate-950 p-5 text-white shadow-xl shadow-emerald-950/10 sm:p-7">
            <div class="pointer-events-none absolute inset-y-0 end-0 w-2/5 bg-linear-to-l from-emerald-500/20 to-transparent"></div>
            <div class="pointer-events-none absolute -end-12 -top-20 h-64 w-64 rounded-full border-[38px] border-emerald-300/10"></div>
            <div class="relative flex flex-col justify-between gap-6 sm:flex-row sm:items-center">
                <div class="min-w-0">
                    <p class="text-xs font-bold tracking-widest text-emerald-300">{{ __('Fiscal year overview') }}</p>
                    <h1 class="mt-2 text-3xl font-black sm:text-4xl">{{ __('Fiscal year') }} {{ localizeNumber($fiscalYear->year) }}</h1>
                    <p class="mt-3 break-words text-lg text-slate-300">{{ $fiscalYear->company->name }}</p>
                </div>
                <span @class(['self-start rounded-xl border px-4 py-2 text-sm font-bold', 'border-emerald-300/40 bg-emerald-400/15 text-emerald-200' => !$fiscalYear->closed_at, 'border-white/20 bg-white/10 text-slate-200' => $fiscalYear->closed_at])>
                    {{ $fiscalYear->closed_at ? __('Closed') : __('Open') }}
                </span>
            </div>
            <div class="relative mt-6 flex flex-wrap gap-2 border-t border-white/10 pt-5">
                @can('fiscal-years.edit')
<<<<<<< HEAD
                    @if ($fiscalYear->closed_at)
                        <button type="button" disabled title="{{ __('This fiscal year is already closed.') }}" class="btn btn-primary btn-sm rounded-xl opacity-60">{{ __('Edit') }}</button>
                    @else
                        <a href="{{ route('fiscal-years.edit', $fiscalYear) }}" class="btn btn-primary btn-sm rounded-xl">{{ __('Edit') }}</a>
                    @endif
=======
                    <a href="{{ route('fiscal-years.edit', $fiscalYear) }}" class="btn btn-primary btn-sm rounded-xl">{{ __('Edit') }}</a>
>>>>>>> 9eb560acf (Refactor user management and fiscal year access)
                @endcan
                @if ($users->contains('id', auth()->id()))
                    @can('companies.closing-wizard')
                        <a href="{{ route('companies.closing-wizard', $fiscalYear) }}" class="btn btn-sm rounded-xl border-white/20 bg-white/10 text-white hover:bg-white/20">{{ __('Review Fiscal Year Closing') }}</a>
                    @endcan
                @endif
            </div>
        </section>

        <section class="grid gap-3 sm:grid-cols-3" aria-label="{{ __('Business usage') }}">
            @foreach ([
                [__('Assigned users'), $fiscalYear->users_count, 'text-indigo-600 dark:text-indigo-400'],
                [__('Documents'), $fiscalYear->documents_count, 'text-sky-600 dark:text-sky-400'],
                [__('Invoices'), $invoicesCount, 'text-amber-600 dark:text-amber-400'],
            ] as [$label, $value, $accent])
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ $label }}</p>
                    <strong class="mt-2 block font-mono text-3xl font-black {{ $accent }}">{{ localizeNumber(number_format($value)) }}</strong>
                </article>
            @endforeach
        </section>

        <div class="grid gap-5 lg:grid-cols-2">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-bold text-slate-900 dark:text-white">{{ __('Fiscal year details') }}</h2>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    @foreach ([
                        [__('Start Date'), formatDate($startDate)],
                        [__('End Date'), formatDate($endDate)],
                        [__('Fiscal year closed on'), $fiscalYear->closed_at ? formatDateTime($fiscalYear->closed_at) : '—'],
                        [__('Closed by'), $fiscalYear->closedBy?->name ?: '—'],
                    ] as [$label, $value])
                        <div>
                            <dt class="text-xs text-slate-500 dark:text-slate-400">{{ $label }}</dt>
                            <dd class="mt-1 break-words text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
                <div class="mt-5 space-y-3 border-t border-slate-200 pt-4 dark:border-slate-800">
                    @foreach ([
                        [__('Income Summary Document'), $fiscalYear->pl_document_id],
                        [__('Closing Document'), $fiscalYear->closing_document_id],
                    ] as [$label, $documentId])
                        @php($document = $closingDocuments->get($documentId))
                        <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                            <span class="text-slate-500 dark:text-slate-400">{{ $label }}</span>
                            @if ($document)
                                <span class="font-semibold text-slate-900 dark:text-slate-100">{{ __('Document Number') }} {{ localizeNumber($document->number) }} · {{ formatDate($document->date) }}</span>
                            @else
                                <span class="text-slate-400">{{ __('Not created') }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="font-bold text-slate-900 dark:text-white">{{ __('Company details') }}</h2>
                    @if (auth()->user()->can('access-super-admin-panel') && auth()->user()->can('companies.show'))
                        <a href="{{ route('companies.show', $fiscalYear->company) }}" class="text-xs font-semibold text-emerald-700 hover:underline dark:text-emerald-300">{{ __('Company overview') }}</a>
                    @endif
                </div>
                <p class="mt-4 break-words font-semibold text-slate-900 dark:text-white">{{ $fiscalYear->company->name }}</p>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    @foreach ([
                        [__('National Code'), $fiscalYear->company->national_code],
                        [__('Economical Code'), $fiscalYear->company->economical_code],
                        [__('Phone'), $fiscalYear->company->phone_number],
                        [__('Postal Code'), $fiscalYear->company->postal_code],
                    ] as [$label, $value])
                        <div>
                            <dt class="text-xs text-slate-500 dark:text-slate-400">{{ $label }}</dt>
                            <dd class="mt-1 break-all text-sm font-semibold text-slate-900 dark:text-slate-100"><bdi dir="ltr">{{ $value ?: '—' }}</bdi></dd>
                        </div>
                    @endforeach
                    <div class="sm:col-span-2">
                        <dt class="text-xs text-slate-500 dark:text-slate-400">{{ __('Address') }}</dt>
                        <dd class="mt-1 break-words text-sm text-slate-900 dark:text-slate-100">{{ $fiscalYear->company->address ?: '—' }}</dd>
                    </div>
                </dl>
            </section>
        </div>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <header class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <h2 class="font-bold text-slate-900 dark:text-white">{{ __('Assigned users') }}</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('People with access to this fiscal year.') }}</p>
            </header>
            <div class="grid divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900 sm:grid-cols-2 xl:grid-cols-3">
                @forelse ($users as $user)
                    <article class="flex min-w-0 gap-3 bg-white p-5 dark:bg-slate-900">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-indigo-100 font-bold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                        <div class="min-w-0">
                            <p class="break-words font-bold text-slate-900 dark:text-white">{{ $user->name }}</p>
                            <p class="mt-0.5 break-all text-xs text-slate-500 dark:text-slate-400"><bdi>{{ $user->email }}</bdi></p>
                            <div class="mt-2 flex flex-wrap gap-1">
                                @forelse ($user->roles as $role)
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $role->name }}</span>
                                @empty
                                    <span class="text-xs text-slate-400">{{ __('No roles assigned') }}</span>
                                @endforelse
                            </div>
                        </div>
                    </article>
                @empty
                    <p class="bg-white px-6 py-12 text-center text-sm text-slate-500 dark:bg-slate-900 dark:text-slate-400 sm:col-span-2 xl:col-span-3">{{ __('No users are assigned to this fiscal year.') }}</p>
                @endforelse
            </div>
        </section>

        @can('fiscal-years.destroy')
            <div class="flex justify-end pb-2">
                <form action="{{ route('fiscal-years.destroy', $fiscalYear) }}" method="POST" onsubmit="return confirm(@js(__('Are you sure you want to delete this fiscal year?')))">
                    @csrf
                    @method('DELETE')
<<<<<<< HEAD
                    <button type="submit" @disabled($fiscalYear->closed_at) title="{{ $fiscalYear->closed_at ? __('This fiscal year is already closed.') : __('Delete') }}" class="btn btn-error btn-outline btn-sm rounded-xl">{{ __('Delete') }}</button>
=======
                    <button type="submit" class="btn btn-error btn-outline btn-sm rounded-xl">{{ __('Delete') }}</button>
>>>>>>> 9eb560acf (Refactor user management and fiscal year access)
                </form>
            </div>
        @endcan
    </div>
</x-platform-layout>
