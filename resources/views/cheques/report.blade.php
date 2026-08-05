@php
    $totalChequeCount = $byStatus->sum(fn ($row) => (int) $row->count);
    $totalChequeAmount = $byStatus->sum(fn ($row) => (float) $row->total);
    $upcomingAmount = $upcoming->sum('amount');
    $statusStyles = [
        'registered' => ['card' => 'border-sky-300 bg-sky-50 dark:border-sky-400/25 dark:bg-sky-400/5', 'accent' => 'bg-sky-500 dark:bg-sky-400', 'badge' => 'border-sky-300 bg-sky-100 text-sky-800 dark:border-sky-400/30 dark:bg-sky-400/10 dark:text-sky-200'],
        'deposited' => ['card' => 'border-cyan-300 bg-cyan-50 dark:border-cyan-400/25 dark:bg-cyan-400/5', 'accent' => 'bg-cyan-500 dark:bg-cyan-400', 'badge' => 'border-cyan-300 bg-cyan-100 text-cyan-800 dark:border-cyan-400/30 dark:bg-cyan-400/10 dark:text-cyan-200'],
        'cleared' => ['card' => 'border-emerald-300 bg-emerald-50 dark:border-emerald-400/25 dark:bg-emerald-400/5', 'accent' => 'bg-emerald-500 dark:bg-emerald-400', 'badge' => 'border-emerald-300 bg-emerald-100 text-emerald-800 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200'],
        'endorsed' => ['card' => 'border-violet-300 bg-violet-50 dark:border-violet-400/25 dark:bg-violet-400/5', 'accent' => 'bg-violet-500 dark:bg-violet-400', 'badge' => 'border-violet-300 bg-violet-100 text-violet-800 dark:border-violet-400/30 dark:bg-violet-400/10 dark:text-violet-200'],
        'bounced' => ['card' => 'border-rose-300 bg-rose-50 dark:border-rose-400/30 dark:bg-rose-400/5', 'accent' => 'bg-rose-500 dark:bg-rose-400', 'badge' => 'border-rose-300 bg-rose-100 text-rose-800 dark:border-rose-400/35 dark:bg-rose-400/10 dark:text-rose-200'],
        'returned' => ['card' => 'border-orange-300 bg-orange-50 dark:border-orange-400/25 dark:bg-orange-400/5', 'accent' => 'bg-orange-500 dark:bg-orange-400', 'badge' => 'border-orange-300 bg-orange-100 text-orange-800 dark:border-orange-400/30 dark:bg-orange-400/10 dark:text-orange-200'],
        'issued' => ['card' => 'border-indigo-300 bg-indigo-50 dark:border-indigo-400/25 dark:bg-indigo-400/5', 'accent' => 'bg-indigo-500 dark:bg-indigo-400', 'badge' => 'border-indigo-300 bg-indigo-100 text-indigo-800 dark:border-indigo-400/30 dark:bg-indigo-400/10 dark:text-indigo-200'],
        'cancelled' => ['card' => 'border-slate-300 bg-slate-50 dark:border-slate-400/25 dark:bg-slate-400/5', 'accent' => 'bg-slate-500 dark:bg-slate-400', 'badge' => 'border-slate-300 bg-slate-200 text-slate-800 dark:border-slate-400/30 dark:bg-slate-400/10 dark:text-slate-200'],
        'guarantee_received' => ['card' => 'border-amber-300 bg-amber-50 dark:border-amber-400/25 dark:bg-amber-400/5', 'accent' => 'bg-amber-500 dark:bg-amber-400', 'badge' => 'border-amber-300 bg-amber-100 text-amber-800 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-200'],
        'guarantee_given' => ['card' => 'border-fuchsia-300 bg-fuchsia-50 dark:border-fuchsia-400/25 dark:bg-fuchsia-400/5', 'accent' => 'bg-fuchsia-500 dark:bg-fuchsia-400', 'badge' => 'border-fuchsia-300 bg-fuchsia-100 text-fuchsia-800 dark:border-fuchsia-400/30 dark:bg-fuchsia-400/10 dark:text-fuchsia-200'],
    ];
@endphp

<x-app-layout :title="__('Cheque Report')">
    {{-- Page Header --}}
    <div class="flex flex-wrap items-center justify-between gap-4 px-1 pb-5">
        <div class="flex min-w-0 items-center gap-3">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                        d="M3 3v18h18M7 16l4-4 3 3 5-7M17 8h2v2" />
                </svg>
            </div>
            <div>
                <h1 class="text-xl font-bold text-base-content">{{ __('Cheque Report') }}</h1>
                <p class="mt-0.5 text-sm text-base-content/50">{{ __('Cheque status and maturity overview') }}</p>
            </div>
        </div>

        <a class="btn btn-ghost btn-sm gap-1.5" href="{{ route('cheques.index') }}">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 19-7-7 7-7" />
            </svg>
            {{ __('Back') }}
        </a>
    </div>

    <div class="space-y-5 px-1 pb-6">
        {{-- Headline figures --}}
        <div class="grid gap-4 md:grid-cols-3">
            <div class="card overflow-hidden border border-sky-200 bg-gradient-to-br from-sky-50 to-blue-100/70 shadow-sm dark:border-sky-400/20 dark:from-base-100 dark:to-sky-400/5">
                <div class="card-body flex-row items-center gap-4 p-5">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary dark:bg-primary/20">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.75 7.5h16.5m-16.5 0v9A2.25 2.25 0 0 0 6 18.75h12a2.25 2.25 0 0 0 2.25-2.25v-9M7.5 12h4.5" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-base-content/45">{{ __('Total cheques') }}</p>
                        <p class="mt-1 text-2xl font-bold text-primary">{{ localizeNumber($totalChequeCount) }}</p>
                    </div>
                </div>
            </div>

            <div class="card overflow-hidden border border-emerald-200 bg-gradient-to-br from-emerald-50 to-teal-100/70 shadow-sm dark:border-emerald-400/20 dark:from-base-100 dark:to-emerald-400/5">
                <div class="card-body flex-row items-center gap-4 p-5">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-success/10 text-success dark:bg-success/20">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8V7m0 9v1m9-5a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-medium text-base-content/45">{{ __('Total cheque amount') }}</p>
                        <p class="mt-1 truncate text-2xl font-bold text-success">{{ formatNumber($totalChequeAmount) }}</p>
                        <p class="text-xs text-base-content/40">{{ config('amir.currency') ?? __('Rial') }}</p>
                    </div>
                </div>
            </div>

            <div class="card overflow-hidden border border-rose-300 bg-gradient-to-br from-rose-50 to-red-100/80 shadow-sm dark:border-rose-400/25 dark:from-base-100 dark:to-rose-400/5">
                <div class="card-body flex-row items-center gap-4 p-5">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-error/10 text-error dark:bg-error/25">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-1.5a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM12 16.5h.008v.008H12V16.5Z" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-medium text-base-content/45">{{ __('Total amount of overdue outstanding cheques') }}</p>
                        <p class="mt-1 truncate text-2xl font-bold text-error">{{ formatNumber($overdue) }}</p>
                        <p class="text-xs text-base-content/40">{{ config('amir.currency') ?? __('Rial') }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Status overview --}}
        <div class="card border border-base-200 bg-base-100 shadow-sm dark:border-base-content/15 dark:bg-base-200/40">
            <div class="card-body p-5 sm:p-6">
                <div class="mb-4 flex items-center gap-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h10M4 18h7" />
                        </svg>
                    </div>
                    <h2 class="font-bold text-base-content">{{ __('Status overview') }}</h2>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                    @foreach (\App\Enums\ChequeType::statuses() as $status)
                        @php
                            $row = $byStatus->get($status->value);
                            $style = $statusStyles[$status->valueName()];
                        @endphp
                        <div class="relative overflow-hidden rounded-xl border p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:shadow-black/20 {{ $style['card'] }}">
                            <span class="absolute inset-y-0 start-0 w-1 {{ $style['accent'] }}"></span>
                            <div class="flex items-center justify-between gap-2">
                                <span class="badge badge-sm {{ $style['badge'] }}">{{ $status->label() }}</span>
                                <span class="text-xl font-bold text-base-content">{{ localizeNumber($row?->count ?? 0) }}</span>
                            </div>
                            <p class="mt-4 truncate text-lg font-semibold text-base-content/80">{{ formatNumber($row?->total ?? 0) }}</p>
                            <p class="mt-0.5 text-xs text-base-content/40">{{ config('amir.currency') ?? __('Rial') }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Upcoming cheques --}}
        <div class="card border border-base-200 bg-base-100 shadow-sm dark:border-base-content/15 dark:bg-base-200/40">
            <div class="card-body p-0">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-base-200 px-5 py-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <h2 class="font-bold text-base-content">{{ __('Due in the next 30 days') }}</h2>
                        <span class="badge badge-ghost">
                            {{ trans_choice(':count cheque|:count cheques', $upcoming->count(), ['count' => localizeNumber($upcoming->count())]) }}
                        </span>
                    </div>
                    <div class="text-end">
                        <p class="text-xs text-base-content/40">{{ __('Total cheque amount') }}</p>
                        <p class="font-bold text-warning">{{ formatNumber($upcomingAmount) }}</p>
                    </div>
                </div>

                @if ($upcoming->count())
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr class="text-base-content/60">
                                    <th>{{ __('Cheque number') }}</th>
                                    <th>{{ __('Cheque serial') }}</th>
                                    <th>{{ __('Account side') }}</th>
                                    <th>{{ __('Due date') }}</th>
                                    <th>{{ __('Amount') }}</th>
                                    <th class="w-12"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($upcoming as $cheque)
                                    <tr class="transition-colors hover:bg-base-200/40 dark:hover:bg-base-content/10">
                                        <td class="font-semibold">
                                            <a class="link link-primary no-underline hover:underline" href="{{ route('cheques.show', $cheque) }}">
                                                {{ localizeNumber($cheque->cheque_number) ?: '—' }}
                                            </a>
                                        </td>
                                        <td>{{ localizeNumber($cheque->serial) ?: '—' }}</td>
                                        <td>
                                            @if ($cheque->customer)
                                                <a href="{{ route('customers.show', $cheque->customer) }}" class="transition hover:text-primary">
                                                    {{ $cheque->customer->name }}
                                                </a>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap font-medium">{{ formatDate($cheque->due_date) }}</td>
                                        <td class="whitespace-nowrap font-semibold">{{ formatNumber($cheque->amount) }}</td>
                                        <td>
                                            <a href="{{ route('cheques.show', $cheque) }}" class="btn btn-ghost btn-square btn-xs" title="{{ __('View') }}">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                </svg>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center px-5 py-16 text-base-content/35">
                        <svg xmlns="http://www.w3.org/2000/svg" class="mb-4 h-14 w-14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m5-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <p class="font-medium">{{ __('No cheques are due in the next 30 days.') }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
