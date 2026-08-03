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
@endphp

<x-app-layout :title="$pageTitle">
    <x-show-message-bags />
    <div class="space-y-4">
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 class="card-title">
                            {{ $cheque->cheque_number ? __('Cheque #:number', ['number' => localizeNumber($cheque->cheque_number)]) : __('Cheque') }}
                        </h1>
                        <span class="badge badge-{{ $cheque->status->color() }} mt-2">{{ $cheque->status->label() }}</span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a class="btn btn-ghost btn-sm" href="{{ route('cheques.index') }}">{{ __('Back') }}</a>
                        @if ($cheque->histories->whereNotNull('from_status')->isEmpty())
                            <a class="btn btn-outline btn-sm" href="{{ route('cheques.edit', $cheque) }}">{{ __('Edit') }}</a>
                        @endif
                        <form method="POST" action="{{ route('cheques.destroy', $cheque) }}">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-error btn-outline btn-sm" onclick="return confirm('{{ __('Delete this cheque and all of its accounting entries and payments?') }}')">{{ __('Delete') }}</button>
                        </form>
                    </div>
                </div>
                <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ([
                        __('Cheque number') => localizeNumber($cheque->cheque_number),
                        __('Cheque serial') => localizeNumber($cheque->serial),
                        __('Sayad number') => localizeNumber($cheque->sayad_number),
                        __('Amount') => formatNumber($cheque->amount),
                        __('Account side') => $cheque->customer?->name,
                        __('Direction') => $cheque->direction->label(),
                        __('Purpose') => $cheque->purpose->label(),
                        __('Issue date') => formatDate($cheque->write_date),
                        __('Due date') => formatDate($cheque->due_date),
                        __('Bank') => $cheque->bankAccount?->bank?->name,
                        __('Bank account') => $cheque->bankAccount?->name,
                        __('Chequebook') => $cheque->chequebook?->displayName(),
                        __('Endorsed to') => $cheque->endorsedTo?->name,
                        __('Description') => $cheque->desc,
                    ] as $label => $value)
                        <div>
                            <div class="text-xs opacity-60">{{ $label }}</div>
                            <div class="mt-1 font-medium">{{ $value ?: '—' }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        @if (count($cheque->availableActions()))
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h2 class="card-title text-base">{{ __('Actions') }}</h2>
                    <div class="grid gap-4 lg:grid-cols-2">
                        @foreach ($cheque->availableActions() as $action)
                            <form method="POST" action="{{ route('cheques.transition', [$cheque, $action]) }}" class="rounded-box border border-base-300 p-4">
                                @csrf
                                <h3 class="font-bold mb-3">{{ $actionLabels[$action] }}</h3>
                                @if ($action === 'deposit')
                                    <select name="bank_account_id" class="select select-bordered select-sm w-full" required>
                                        <option value="">{{ __('Destination bank account') }}</option>
                                        @foreach ($bankAccounts as $account)
                                            <option value="{{ $account->id }}">{{ $account->name }}</option>
                                        @endforeach
                                    </select>
                                @elseif($action === 'endorse')
                                    @php
                                        $initialEndorseeId = old('account_side_id');
                                        $initialEndorseeValue = $initialEndorseeId ? "customer-{$initialEndorseeId}" : '';
                                    @endphp
                                    <div x-data="{
                                        accountSideId: @js((string) $initialEndorseeId),
                                        selectedAccountSide: @js($initialEndorseeValue),
                                    }">
                                        <x-select-box :options="[['headerGroup' => 'customer', 'options' => $accountSides]]"
                                            x-model="selectedAccountSide" placeholder="{{ __('Vendor / endorsee') }}"
                                            @selected="accountSideId = $event.detail.id" />
                                        <x-input name="account_side_id" x-bind:value="accountSideId" hidden />
                                    </div>
                                @endif
                                <div class="grid grid-cols-2 gap-2 mt-2">
                                    <x-date-picker name="date" :id="'date_' . $action" :value="toEnglish(formatDate(now()))" :placeholder="__('Action date')" />
                                    <x-text-input name="description" placeholder="{{ __('Note') }}" />
                                </div>
                                <button class="btn btn-primary btn-sm mt-3" onclick="return confirm('{{ __('Continue with this action?') }}')">{{ $actionLabels[$action] }}</button>
                            </form>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <h2 class="card-title text-base">{{ __('Cheque history') }}</h2>
                <div class="mt-4 space-y-3">
                    @foreach ($cheque->histories->reverse() as $history)
                        <div class="border-s-4 border-primary ps-4 py-1">
                            <div class="flex flex-wrap gap-2">
                                <strong>{{ $history->from_status?->label() ?? '—' }} → {{ $history->to_status->label() }}</strong>
                                <span>{{ formatDateTime($history->created_at) }}</span>
                            </div>
                            <div class="text-sm opacity-70">{{ $history->user?->name }}
                                @if ($history->document_id)
                                    <a class="link" href="{{ route('documents.show', $history->document_id) }}">{{ __('Document') }}</a>
                                @endif
                                @if ($history->desc)
                                    {{ $history->desc }}
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
