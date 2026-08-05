<x-app-layout :title="__('Chequebooks')">
    <x-show-message-bags />

    {{-- Page Header --}}
    <div class="flex flex-wrap items-center justify-between gap-4 px-1 pb-5">
        <div class="min-w-48">
            <h1 class="text-xl font-bold text-base-content">{{ __('Chequebooks') }}</h1>
            <p class="mt-0.5 text-sm text-base-content/50">{{ __('Manage chequebooks for payable cheques') }}</p>
        </div>

        <a href="{{ route('chequebooks.create') }}" class="btn btn-primary btn-sm">
            {{ __('Create chequebook') }}
        </a>
    </div>

    {{-- Chequebook List --}}
    <div class="card mx-1 mb-6 border border-base-200 bg-base-100 shadow-sm">
        <div class="card-body p-0">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-base-200 px-5 py-4">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-base font-bold text-base-content">{{ __('Chequebooks') }}</h2>
                    <span class="badge badge-ghost">
                        {{ localizeNumber($chequebooks->total()) }} {{ __('records') }}
                    </span>
                </div>

                <form action="{{ route('chequebooks.index') }}" method="GET" class="flex flex-wrap items-center gap-2">
                    <div class="w-40 max-w-full [&_.input]:input-sm">
                        <x-input type="text" name="serial_prefix" value="{{ request('serial_prefix') }}" placeholder="{{ __('Serial prefix') }}" />
                    </div>

                    <select name="bank_account_id" class="select select-sm w-48 max-w-full">
                        <option value="">{{ __('All bank accounts') }}</option>
                        @foreach ($bankAccounts as $bankAccount)
                            <option value="{{ $bankAccount->id }}" @selected((string) request('bank_account_id') === (string) $bankAccount->id)>
                                {{ $bankAccount->name }}@if ($bankAccount->bank) — {{ $bankAccount->bank->name }}@endif
                            </option>
                        @endforeach
                    </select>

                    <select name="availability" class="select select-sm w-44 max-w-full">
                        <option value="">{{ __('All chequebooks') }}</option>
                        <option value="available" @selected(request('availability') === 'available')>{{ __('Available leaves') }}</option>
                        <option value="exhausted" @selected(request('availability') === 'exhausted')>{{ __('Exhausted chequebooks') }}</option>
                    </select>

                    <button type="submit" class="btn btn-sm btn-neutral">{{ __('Search') }}</button>

                    @if (request()->hasAny(['serial_prefix', 'bank_account_id', 'availability']))
                        <a href="{{ route('chequebooks.index') }}" class="btn btn-sm btn-ghost">{{ __('Reset') }}</a>
                    @endif
                </form>
            </div>

            <div class="p-4 sm:p-5">
                @if ($chequebooks->count())
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($chequebooks as $chequebook)
                            @php
                                $remainingLeaves = max(0, $chequebook->last_leaf - $chequebook->next_leaf + 1);
                                $leafCount = max(1, $chequebook->last_leaf - $chequebook->first_leaf + 1);
                                $usedPercentage = min(100, max(0, (($leafCount - $remainingLeaves) / $leafCount) * 100));
                            @endphp

                            <div class="card rounded-lg border border-base-200 bg-base-100 shadow-sm transition hover:border-primary/30 hover:shadow-md dark:bg-base-200/40">
                                <div class="card-body gap-4 p-4">
                                    <div class="flex items-start gap-3">
                                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                                    d="M3.75 7.5h16.5m-16.5 0A2.25 2.25 0 0 1 6 5.25h12a2.25 2.25 0 0 1 2.25 2.25m-16.5 0v9A2.25 2.25 0 0 0 6 18.75h12a2.25 2.25 0 0 0 2.25-2.25v-9M7.5 12h4.5" />
                                            </svg>
                                        </div>

                                        <div class="min-w-0 flex-1">
                                            <h3 class="truncate font-bold text-base-content">
                                                @can('chequebooks.show')
                                                    <a href="{{ route('chequebooks.show', $chequebook) }}" class="hover:text-primary">
                                                        {{ $chequebook->bankAccount?->name ?: __('Bank account') }}
                                                    </a>
                                                @else
                                                    {{ $chequebook->bankAccount?->name ?: __('Bank account') }}
                                                @endcan
                                            </h3>
                                            <p class="mt-0.5 truncate text-sm text-base-content/50">
                                                {{ $chequebook->bankAccount?->bank?->name ?: '—' }}
                                            </p>
                                        </div>

                                        <span class="tooltip tooltip-bottom shrink-0" data-tip="{{ __('Serial prefix') }}">
                                            <span class="badge badge-outline font-mono" dir="ltr">
                                                {{ $chequebook->serial_prefix ?: '—' }}
                                            </span>
                                        </span>
                                    </div>

                                    <div class="grid grid-cols-3 divide-x divide-x-reverse divide-base-200 rounded-lg bg-base-200/50 py-3 text-center">
                                        <div class="px-2">
                                            <p class="text-xs text-base-content/50">{{ __('Serial range') }}</p>
                                            <p class="mt-1 text-sm font-semibold" dir="ltr">
                                                {{ localizeNumber($chequebook->first_leaf) }}–{{ localizeNumber($chequebook->last_leaf) }}
                                            </p>
                                        </div>
                                        <div class="px-2">
                                            <p class="text-xs text-base-content/50">{{ __('Next leaf') }}</p>
                                            <p class="mt-1 text-sm font-semibold">{{ localizeNumber($chequebook->next_leaf) }}</p>
                                        </div>
                                        <div class="px-2">
                                            <p class="text-xs text-base-content/50">{{ __('Cheques') }}</p>
                                            <p class="mt-1 text-sm font-semibold">{{ localizeNumber($chequebook->cheques_count) }}</p>
                                        </div>
                                    </div>

                                    <progress class="progress progress-primary h-1.5 w-full" value="{{ $usedPercentage }}" max="100"></progress>

                                    <div class="card-actions items-center justify-between border-t border-base-200 pt-3">
                                        @can('cheques.create')
                                            @if ($remainingLeaves > 0)
                                                <a href="{{ route('cheques.create', [
                                                    'direction' => \App\Enums\ChequeType::PAYABLE->value,
                                                    'bank_account_id' => $chequebook->bank_account_id,
                                                    'chequebook_id' => $chequebook->id,
                                                ]) }}" class="btn btn-primary btn-xs gap-1">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6" />
                                                    </svg>
                                                    {{ __('Register cheque') }}
                                                </a>
                                            @else
                                                <span class="tooltip" data-tip="{{ __('The selected chequebook has no unused leaves.') }}">
                                                    <button type="button" class="btn btn-primary btn-xs gap-1" disabled>
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6" />
                                                        </svg>
                                                        {{ __('Register cheque') }}
                                                    </button>
                                                </span>
                                            @endif
                                        @endcan

                                        <div class="ms-auto flex items-center gap-1">
                                            @can('chequebooks.show')
                                                <a href="{{ route('chequebooks.show', $chequebook) }}" class="btn btn-xs btn-ghost gap-1">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                    </svg>
                                                    {{ __('View') }}
                                                </a>
                                            @endcan
                                            @can('chequebooks.edit')
                                                <a href="{{ route('chequebooks.edit', $chequebook) }}" class="btn btn-xs btn-ghost gap-1">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L8.582 18.07a4.5 4.5 0 0 1-1.897 1.13L3 20.25l1.05-3.685a4.5 4.5 0 0 1 1.13-1.897l11.682-11.681Z" />
                                                    </svg>
                                                    {{ __('Edit') }}
                                                </a>
                                            @endcan
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center py-16 text-base-content/35">
                        <svg xmlns="http://www.w3.org/2000/svg" class="mb-4 h-16 w-16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3.75 7.5h16.5m-16.5 0A2.25 2.25 0 0 1 6 5.25h12a2.25 2.25 0 0 1 2.25 2.25m-16.5 0v9A2.25 2.25 0 0 0 6 18.75h12a2.25 2.25 0 0 0 2.25-2.25v-9M7.5 12h4.5" />
                        </svg>
                        <p class="text-base font-medium">{{ __('No chequebooks found.') }}</p>
                    </div>
                @endif
            </div>

            @if ($chequebooks->hasPages())
                <div class="border-t border-base-200 px-5 py-4">
                    {!! $chequebooks->withQueryString()->links() !!}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
