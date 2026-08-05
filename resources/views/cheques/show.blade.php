@php
    $actionLabels = [
        'deposit' => __('Deposit to bank'),
        'clear' => __('Clear / cash'),
        'endorse' => __('Endorse / transfer'),
        'bounce' => __('Mark dishonoured'),
        'return' => __('Return to customer'),
        'cancel' => __('Cancel / take back'),
        'execute' => __('Execute guarantee'),
    ];

    $pageTitle = $cheque->cheque_number ? __('Cheque #:number', ['number' => $cheque->cheque_number]) : __('Cheque');
    $canEdit = $cheque->histories->whereNotNull('from_status')->isEmpty();
@endphp

<x-app-layout :title="$pageTitle">
    <x-show-message-bags />

    <div class="space-y-5 px-1 pb-6">
        {{-- Cheque Header --}}
        <div class="card overflow-hidden border border-primary/15 bg-base-100 shadow-sm">
            <div
                class="relative overflow-hidden bg-gradient-to-br from-primary/10 via-base-100 to-secondary/10 p-5 sm:p-7">
                <div class="pointer-events-none absolute -end-20 -top-24 h-64 w-64 rounded-full bg-primary/10 blur-3xl">
                </div>

                <div class="relative flex flex-wrap items-start justify-between gap-6">
                    <div class="flex min-w-0 items-center gap-4">
                        <div
                            class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-primary text-primary-content shadow-lg shadow-primary/20">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                    d="M3.75 7.5h16.5m-16.5 0A2.25 2.25 0 0 1 6 5.25h12a2.25 2.25 0 0 1 2.25 2.25m-16.5 0v9A2.25 2.25 0 0 0 6 18.75h12a2.25 2.25 0 0 0 2.25-2.25v-9M7.5 12h4.5" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h1 class="text-xl font-bold text-base-content sm:text-2xl">
                                    {{ $cheque->cheque_number ? __('Cheque #:number', ['number' => localizeNumber($cheque->cheque_number)]) : __('Cheque') }}
                                </h1>
                                <span class="badge badge-{{ $cheque->status->color() }} badge-outline">
                                    {{ $cheque->status->label() }}
                                </span>
                            </div>
                            <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-base-content/55">
                                <span>{{ $cheque->direction->label() }}</span>
                                <span class="h-1 w-1 rounded-full bg-base-content/25"></span>
                                <span>{{ $cheque->purpose->label() }}</span>
                                @if ($cheque->customer)
                                    <span class="h-1 w-1 rounded-full bg-base-content/25"></span>
                                    <a href="{{ route('customers.show', $cheque->customer) }}"
                                        class="transition hover:text-primary">
                                        {{ $cheque->customer->name }}
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="flex max-w-full flex-wrap items-center justify-end gap-2">
                        <div
                            class="me-1 flex h-8 items-center gap-2 rounded-lg border border-base-content/10 bg-base-100/70 px-3 shadow-sm backdrop-blur">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0 text-base-content/40"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8V7m0 9v1" />
                            </svg>
                            <span
                                class="whitespace-nowrap text-xs font-medium text-base-content/50">{{ __('Amount') }}</span>
                            <span class="h-4 w-px bg-base-content/15"></span>
                            <span class="whitespace-nowrap text-sm font-bold text-primary">
                                {{ formatNumber($cheque->amount) }}
                                <span
                                    class="text-xs font-normal text-base-content/40">{{ config('amir.currency') ?? __('Rial') }}</span>
                            </span>
                        </div>

                        @if ($canEdit)
                            <a class="btn btn-primary btn-sm gap-1.5" href="{{ route('cheques.edit', $cheque) }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L8.582 18.07a4.5 4.5 0 0 1-1.897 1.13L3 20.25l1.05-3.685a4.5 4.5 0 0 1 1.13-1.897l11.682-11.681Z" />
                                </svg>
                                {{ __('Edit') }}
                            </a>
                        @endif
                        <form method="POST" action="{{ route('cheques.destroy', $cheque) }}">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-error btn-outline btn-sm gap-1.5"
                                onclick="return confirm('{{ __('Delete this cheque and all of its accounting entries and payments?') }}')">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21L18.16 19.673A2.25 2.25 0 0 1 15.916 21H8.084a2.25 2.25 0 0 1-2.244-1.827L4.772 5.79m14.456 0H4.772m10.978 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916" />
                                </svg>
                                {{ __('Delete') }}
                            </button>
                        </form>
                        <a class="btn btn-ghost btn-sm gap-1.5" href="{{ route('cheques.index') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 rtl:rotate-180" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="m15 19-7-7 7-7" />
                            </svg>
                            {{ __('Back') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Cheque Information --}}
        <div class="card border border-base-200 bg-base-100 shadow-sm">
            <div class="card-body p-5 sm:p-6">
                <div class="mb-1 flex items-center gap-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-info/10 text-info">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <h2 class="font-bold text-base-content">{{ __('Cheque information') }}</h2>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ([
        ['label' => __('Cheque serial'), 'value' => localizeNumber($cheque->serial), 'url' => null],
        ['label' => __('Sayad number'), 'value' => localizeNumber($cheque->sayad_number), 'url' => null],
        ['label' => __('Issue date'), 'value' => formatDate($cheque->write_date), 'url' => null],
        ['label' => __('Due date'), 'value' => formatDate($cheque->due_date), 'url' => null],
                        ['label' => __('Bank'), 'value' => $cheque->bankAccount?->bank?->name, 'url' => null],
        ['label' => __('Bank account'), 'value' => $cheque->bankAccount?->name, 'url' => $cheque->bankAccount ? route('bank-accounts.show', $cheque->bankAccount) : null],
        ['label' => __('Chequebook'), 'value' => $cheque->chequebook?->displayName(), 'url' => $cheque->chequebook ? route('chequebooks.show', $cheque->chequebook) : null],
        ['label' => __('Endorsed to'), 'value' => $cheque->endorsedTo?->name, 'url' => $cheque->endorsedTo ? route('customers.show', $cheque->endorsedTo) : null],
    ] as $item)
                        <div class="min-w-0 rounded-xl border border-base-200 bg-base-200/25 p-4">
                            <p class="text-xs font-medium text-base-content/45">{{ $item['label'] }}</p>
                            <p class="mt-1 truncate font-semibold text-base-content">
                                @if ($item['url'])
                                    <a href="{{ $item['url'] }}"
                                        class="transition hover:text-primary hover:underline">{{ $item['value'] }}</a>
                                @else
                                    {{ $item['value'] ?: '—' }}
                                @endif
                            </p>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4 flex gap-3 rounded-xl bg-base-200/50 p-4 text-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-5 w-5 shrink-0 text-info" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    <div class="min-w-0">
                        <p class="font-semibold">{{ __('Description') }}</p>
                        <p class="mt-1 whitespace-pre-line text-base-content/60">{{ $cheque->desc ?: '—' }}</p>
                    </div>
                </div>
            </div>
        </div>

        @if (count($cheque->availableActions()))
            {{-- Lifecycle Actions --}}
            <div class="card border border-base-200 bg-base-100 shadow-sm">
                <div class="card-body p-5 sm:p-6">
                    <div class="mb-1 flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 6v6l4 2m5-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        </div>
                        <h2 class="font-bold text-base-content">{{ __('Actions') }}</h2>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-2">
                        @foreach ($cheque->availableActions() as $action)
                            <form method="POST" action="{{ route('cheques.transition', [$cheque, $action]) }}"
                                class="rounded-xl border border-base-200 bg-base-200/25 p-4 transition hover:border-primary/25 hover:shadow-sm
                                    [&_.input]:h-10 [&_.input]:min-h-10 [&_.input]:max-h-10">
                                @csrf
                                <h3 class="mb-3 font-bold">{{ $actionLabels[$action] }}</h3>
                                @if ($action === 'deposit')
                                    <select name="bank_account_id" class="select select-sm w-full border-slate-400"
                                        required>
                                        <option value="">{{ __('Destination bank account') }}</option>
                                        @foreach ($bankAccounts as $account)
                                            <option value="{{ $account->id }}">{{ $account->name }}</option>
                                        @endforeach
                                    </select>
                                @elseif($action === 'endorse')
                                    @php
                                        $initialEndorseeId = old('account_side_id');
                                        $initialEndorseeValue = $initialEndorseeId
                                            ? "customer-{$initialEndorseeId}"
                                            : '';
                                    @endphp
                                    <div x-data="{
                                        accountSideId: @js((string) $initialEndorseeId),
                                        selectedAccountSide: @js($initialEndorseeValue),
                                    }">
                                        <x-select-box :options="[['headerGroup' => 'customer', 'options' => $accountSides]]" x-model="selectedAccountSide"
                                            placeholder="{{ __('Vendor / endorsee') }}"
                                            @selected="accountSideId = $event.detail.id" />
                                        <x-input name="account_side_id" x-bind:value="accountSideId" hidden />
                                    </div>
                                @endif
                                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                    <x-date-picker name="date" :id="'date_' . $action" :value="toEnglish(formatDate(now()))"
                                        :placeholder="__('Action date')" />
                                    <x-text-input name="description" placeholder="{{ __('Note') }}" />
                                </div>
                                <div class="mt-3 flex justify-end">
                                    <button class="btn btn-primary btn-sm"
                                        onclick="return confirm('{{ __('Continue with this action?') }}')">
                                        {{ $actionLabels[$action] }}
                                    </button>
                                </div>
                            </form>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        {{-- History --}}
        <div class="card border border-base-200 bg-base-100 shadow-sm">
            <div class="card-body p-5 sm:p-6">
                <div class="mb-3 flex items-center gap-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-secondary/10 text-secondary">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <h2 class="font-bold text-base-content">{{ __('Cheque history') }}</h2>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ($cheque->histories->reverse() as $history)
                        <div class="group relative overflow-hidden rounded-xl border border-base-200 bg-gradient-to-br from-base-100 to-base-200/50 p-4 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-md">
                            <span class="absolute inset-y-0 start-0 w-1 bg-primary/60"></span>

                            <div class="flex items-start justify-between gap-3">
                                <div class="flex min-w-0 items-center gap-2 text-sm">
                                    <span class="truncate text-base-content/50">{{ $history->from_status?->label() ?? '—' }}</span>
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-base-200 text-base-content/35 transition group-hover:bg-primary/10 group-hover:text-primary">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 18 6-6-6-6" />
                                        </svg>
                                    </span>
                                    <span class="badge badge-{{ $history->to_status->color() }} badge-sm shrink-0 font-semibold">
                                        {{ $history->to_status->label() }}
                                    </span>
                                </div>
                                @if ($history->document_id)
                                    <a class="btn btn-ghost btn-square btn-xs shrink-0" title="{{ __('Document') }}"
                                        href="{{ route('documents.show', $history->document_id) }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H8.25m0 12.75h7.5m-7.5 3h7.5M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.625a9 9 0 0 0-9-9Z" />
                                        </svg>
                                    </a>
                                @endif
                            </div>

                            <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-base-content/45">
                                <span class="flex items-center gap-1.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2m5-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                    {{ formatDateTime($history->created_at) }}
                                </span>
                                @if ($history->user)
                                    <span class="flex min-w-0 items-center gap-1.5">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.1a7.5 7.5 0 0 1 15 0 17.9 17.9 0 0 1-15 0Z" />
                                        </svg>
                                        <span class="truncate">{{ $history->user->name }}</span>
                                    </span>
                                @endif
                            </div>

                            @if ($history->desc)
                                <p class="mt-3 rounded-lg bg-base-200/60 px-3 py-2 text-xs leading-relaxed text-base-content/60">
                                    {{ $history->desc }}
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
