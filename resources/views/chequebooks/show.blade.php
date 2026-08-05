@php
    $remainingLeaves = max(0, $chequebook->last_leaf - $chequebook->next_leaf + 1);
    $leafCount = max(1, $chequebook->last_leaf - $chequebook->first_leaf + 1);
    $usedLeaves = min($leafCount, max(0, $chequebook->next_leaf - $chequebook->first_leaf));
    $usedPercentage = min(100, max(0, (($leafCount - $remainingLeaves) / $leafCount) * 100));
    $isExhausted = $remainingLeaves === 0;
@endphp

<x-app-layout :title="__('Chequebook')">
    <x-show-message-bags />

    <div class="space-y-5 px-1 pb-6">
        {{-- Chequebook Summary --}}
        <div class="card overflow-hidden border border-primary/15 bg-base-100 shadow-sm">
            <div class="relative overflow-hidden bg-gradient-to-br from-primary/10 via-base-100 to-secondary/10 p-5 sm:p-7">
                <div class="pointer-events-none absolute -end-16 -top-20 h-56 w-56 rounded-full bg-primary/10 blur-3xl"></div>
                <div class="relative flex flex-wrap items-start justify-between gap-6">
                    <div class="flex min-w-0 items-center gap-4">
                        <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-primary text-primary-content shadow-lg shadow-primary/20">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                    d="M3.75 7.5h16.5m-16.5 0A2.25 2.25 0 0 1 6 5.25h12a2.25 2.25 0 0 1 2.25 2.25m-16.5 0v9A2.25 2.25 0 0 0 6 18.75h12a2.25 2.25 0 0 0 2.25-2.25v-9M7.5 12h4.5" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                @if ($chequebook->bankAccount)
                                    <a href="{{ route('bank-accounts.show', $chequebook->bankAccount) }}"
                                        class="truncate text-xl font-bold text-base-content transition hover:text-primary sm:text-2xl">
                                        {{ $chequebook->bankAccount->name }}
                                    </a>
                                @else
                                    <h2 class="text-xl font-bold sm:text-2xl">{{ __('Chequebook') }}</h2>
                                @endif
                                <span class="badge {{ $isExhausted ? 'badge-error' : 'badge-success' }} badge-outline">
                                    {{ $isExhausted ? __('Exhausted') : __('Available') }}
                                </span>
                            </div>
                            @if ($chequebook->bankAccount?->bank)
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M5 10v8m4-8v8m6-8v8m4-8v8M3 18h18M12 3 3 7h18l-9-4Z" />
                                    </svg>
                                    {{ $chequebook->bankAccount->bank->name }}
                            @endif
                        </div>
                    </div>

                    <div class="flex max-w-full flex-wrap items-center justify-end gap-2">
                        <div class="me-1 flex h-8 items-center gap-2 rounded-lg border border-base-content/10 bg-base-100/70 px-3 shadow-sm backdrop-blur">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0 text-base-content/40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20 11 4m2 16 4-16M6 9h14M4 15h14" />
                            </svg>
                            <span class="whitespace-nowrap text-xs font-medium text-base-content/50">{{ __('Serial prefix') }}</span>
                            <span class="h-4 w-px bg-base-content/15"></span>
                            <span class="min-w-5 font-mono text-sm font-bold tracking-wide text-base-content" dir="ltr">
                                {{ $chequebook->serial_prefix ?: '—' }}
                            </span>
                        </div>
                    @can('cheques.create')
                        @if ($isExhausted)
                            <span class="tooltip" data-tip="{{ __('The selected chequebook has no unused leaves.') }}">
                                <button type="button" class="btn btn-success btn-sm gap-1.5" disabled>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6" />
                                    </svg>
                                    {{ __('Register cheque') }}
                                </button>
                            </span>
                        @else
                            <a href="{{ route('cheques.create', [
                                'direction' => \App\Enums\ChequeType::PAYABLE->value,
                                'bank_account_id' => $chequebook->bank_account_id,
                                'chequebook_id' => $chequebook->id,
                            ]) }}" class="btn btn-success btn-sm gap-1.5">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6" />
                                </svg>
                                {{ __('Register cheque') }}
                            </a>
                        @endif
                    @endcan
                    @can('chequebooks.edit')
                        <a href="{{ route('chequebooks.edit', $chequebook) }}" class="btn btn-primary btn-sm gap-1.5">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L8.582 18.07a4.5 4.5 0 0 1-1.897 1.13L3 20.25l1.05-3.685a4.5 4.5 0 0 1 1.13-1.897l11.682-11.681Z" />
                            </svg>
                            {{ __('Edit') }}
                        </a>
                    @endcan
                    @can('chequebooks.destroy')
                        <form method="POST" action="{{ route('chequebooks.destroy', $chequebook) }}">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-error btn-outline btn-sm gap-1.5"
                                onclick="return confirm('{{ __('Delete this chequebook? Its cheques will be preserved and unlinked.') }}')">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21L18.16 19.673A2.25 2.25 0 0 1 15.916 21H8.084a2.25 2.25 0 0 1-2.244-1.827L4.772 5.79m14.456 0H4.772m10.978 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916" />
                                </svg>
                                {{ __('Delete') }}
                            </button>
                        </form>
                    @endcan
                     <a href="{{ route('chequebooks.index') }}" class="btn btn-ghost btn-sm gap-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 19-7-7 7-7" />
                        </svg>
                        {{ __('Back') }}
                    </a>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 border-t border-base-200 sm:grid-cols-4">
                @foreach ([
                    ['label' => __('Total leaves'), 'value' => $leafCount, 'tone' => 'text-info'],
                    ['label' => __('Used leaves'), 'value' => $usedLeaves, 'tone' => 'text-warning'],
                    ['label' => __('Remaining leaves'), 'value' => $remainingLeaves, 'tone' => $isExhausted ? 'text-error' : 'text-success'],
                    ['label' => __('Cheques'), 'value' => $cheques->total(), 'tone' => 'text-primary'],
                ] as $stat)
                    <div class="border-base-200 p-4 text-center even:border-s sm:border-s sm:first:border-s-0">
                        <p class="text-xs text-base-content/45">{{ $stat['label'] }}</p>
                        <p class="mt-1 text-2xl font-bold {{ $stat['tone'] }}">{{ localizeNumber($stat['value']) }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="grid gap-5 lg:grid-cols-[1fr_20rem]">
            <div class="card border border-base-200 bg-base-100 shadow-sm">
                <div class="card-body p-5">
                    <h2 class="text-base font-bold">{{ __('Chequebook details') }}</h2>
                    <div class="mt-1 grid gap-3 sm:grid-cols-3">
                        @foreach ([
                            ['label' => __('First leaf'), 'value' => $chequebook->first_leaf],
                            ['label' => __('Last leaf'), 'value' => $chequebook->last_leaf],
                            ['label' => __('Next leaf'), 'value' => $chequebook->next_leaf],
                        ] as $item)
                            <div class="rounded-xl border border-base-200 bg-base-200/30 p-4">
                                <p class="text-xs text-base-content/45">{{ $item['label'] }}</p>
                                <p class="mt-1 text-lg font-bold">{{ localizeNumber($item['value']) }}</p>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 flex gap-3 rounded-xl bg-base-200/50 p-4 text-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-5 w-5 shrink-0 text-info" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <div class="min-w-0">
                            <p class="font-semibold">{{ __('Description') }}</p>
                            <p class="mt-1 whitespace-pre-line text-base-content/60">{{ $chequebook->desc ?: '—' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border border-base-200 bg-base-100 shadow-sm">
                <div class="card-body items-center justify-center p-5 text-center">
                    <div class="radial-progress {{ $isExhausted ? 'text-error' : 'text-primary' }}"
                        style="--value: {{ round($usedPercentage) }}; --size: 8rem; --thickness: 0.65rem;"
                        role="progressbar" aria-valuenow="{{ round($usedPercentage) }}">
                        <span class="text-xl font-bold">{{ localizeNumber(round($usedPercentage)) }}%</span>
                    </div>
                    <h2 class="mt-2 font-bold">{{ __('Cheque usage') }}</h2>
                    <p class="text-sm text-base-content/45" dir="ltr">
                        {{ localizeNumber($chequebook->first_leaf) }} — {{ localizeNumber($chequebook->last_leaf) }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Associated Cheques --}}
        <div class="card border border-base-200 bg-base-100 shadow-sm">
            <div class="card-body p-0">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-base-200 px-5 py-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <h2 class="text-base font-bold text-base-content">{{ __('Cheques') }}</h2>
                        <span class="badge badge-ghost">
                            {{ localizeNumber($cheques->total()) }} {{ __('records') }}
                        </span>
                    </div>
                    @can('cheques.create')
                        @unless ($isExhausted)
                            <a href="{{ route('cheques.create', [
                                'direction' => \App\Enums\ChequeType::PAYABLE->value,
                                'bank_account_id' => $chequebook->bank_account_id,
                                'chequebook_id' => $chequebook->id,
                            ]) }}" class="btn btn-primary btn-xs gap-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6" />
                                </svg>
                                {{ __('Register cheque') }}
                            </a>
                        @endunless
                    @endcan
                </div>

                @if ($cheques->count())
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr class="text-base-content/60">
                                    <th>{{ __('Cheque number') }}</th>
                                    <th>{{ __('Account side') }}</th>
                                    <th>{{ __('Due date') }}</th>
                                    <th>{{ __('Amount') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th class="text-end">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($cheques as $cheque)
                                    <tr class="transition-colors hover:bg-base-200/40">
                                        <td class="font-semibold">
                                            <a class="link link-primary no-underline hover:underline"
                                                href="{{ route('cheques.show', $cheque) }}">
                                                {{ localizeNumber($cheque->cheque_number) ?: '—' }}
                                            </a>
                                        </td>
                                        <td>{{ $cheque->customer?->name ?? '—' }}</td>
                                        <td class="whitespace-nowrap">{{ formatDate($cheque->due_date) }}</td>
                                        <td class="whitespace-nowrap font-semibold">{{ formatNumber($cheque->amount) }}
                                        </td>
                                        <td>
                                            <span
                                                class="badge badge-{{ $cheque->status->color() }} badge-sm whitespace-nowrap">
                                                {{ $cheque->status->label() }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <div class="inline-flex items-center gap-1" dir="ltr">
                                                <a href="{{ route('cheques.show', $cheque) }}"
                                                    class="btn btn-ghost btn-square btn-xs" title="{{ __('View') }}">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                    </svg>
                                                </a>
                                                @can('cheques.edit')
                                                    @if (! $cheque->has_lifecycle_actions)
                                                        <a href="{{ route('cheques.edit', $cheque) }}"
                                                            class="btn btn-ghost btn-square btn-xs" title="{{ __('Edit') }}">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                    d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L8.582 18.07a4.5 4.5 0 0 1-1.897 1.13L3 20.25l1.05-3.685a4.5 4.5 0 0 1 1.13-1.897l11.682-11.681Z" />
                                                            </svg>
                                                        </a>
                                                    @endif
                                                @endcan
                                                @can('cheques.destroy')
                                                    <form method="POST" action="{{ route('cheques.destroy', $cheque) }}"
                                                        onsubmit="return confirm('{{ __('Delete this cheque and all of its accounting entries and payments?') }}')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-ghost btn-square btn-xs text-error" title="{{ __('Delete') }}">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                    d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21L18.16 19.673A2.25 2.25 0 0 1 15.916 21H8.084a2.25 2.25 0 0 1-2.244-1.827L4.772 5.79m14.456 0H4.772m10.978 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916" />
                                                            </svg>
                                                        </button>
                                                    </form>
                                                @endcan
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center px-5 py-16 text-base-content/35">
                        <svg xmlns="http://www.w3.org/2000/svg" class="mb-4 h-14 w-14" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3.75 7.5h16.5m-16.5 0A2.25 2.25 0 0 1 6 5.25h12a2.25 2.25 0 0 1 2.25 2.25m-16.5 0v9A2.25 2.25 0 0 0 6 18.75h12a2.25 2.25 0 0 0 2.25-2.25v-9M7.5 12h4.5" />
                        </svg>
                        <p class="text-base font-medium">{{ __('No cheques are associated with this chequebook.') }}
                        </p>
                    </div>
                @endif

                @if ($cheques->hasPages())
                    <div class="border-t border-base-200 px-5 py-4">
                        {!! $cheques->withQueryString()->links() !!}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
