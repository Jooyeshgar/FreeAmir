<x-app-layout :title="__('cheques cheque_number', ['serial' => $cheque->serial])">
    <x-show-message-bags />
    <div class="space-y-4">
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 class="card-title">{{ __('cheques cheque_number', ['serial' => localizeNumber($cheque->serial)]) }}</h1>
                        <span class="badge badge-{{ $cheque->status->color() }} mt-2">{{ $cheque->status->label() }}</span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a class="btn btn-ghost btn-sm" href="{{ route('cheques.index') }}">{{ __('cheques back') }}</a>
                        @if ($cheque->histories->whereNotNull('from_status')->isEmpty())
                            <a class="btn btn-outline btn-sm" href="{{ route('cheques.edit', $cheque) }}">{{ __('cheques edit') }}</a>
                        @endif
                        @if ($cheque->direction === \App\Enums\ChequeType::PAYABLE)
                            <a class="btn btn-outline btn-sm" target="_blank" href="{{ route('cheques.print', $cheque) }}">{{ __('cheques print') }}</a>
                        @endif
                        <form method="POST" action="{{ route('cheques.destroy', $cheque) }}">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="version" value="{{ $cheque->version }}">
                            <button class="btn btn-error btn-outline btn-sm" onclick="return confirm('{{ __('cheques confirm_delete') }}')">{{ __('cheques delete') }}</button>
                        </form>
                    </div>
                </div>
                <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ([
                        __('cheques fields sayad') => localizeNumber($cheque->sayad_number),
                        __('cheques fields amount') => formatNumber($cheque->amount),
                        __('cheques fields party') => $cheque->party?->name,
                        __('cheques fields direction') => $cheque->direction->label(),
                        __('cheques fields purpose') => $cheque->purpose->label(),
                        __('cheques fields issue_date') => formatDate($cheque->write_date),
                        __('cheques fields due_date') => formatDate($cheque->due_date),
                        __('cheques fields bank') => $cheque->bank?->name,
                        __('cheques fields bank_account') => $cheque->bankAccount?->name,
                        __('cheques fields branch') => trim(($cheque->branch_name ?? '') . ' ' . ($cheque->branch_city ?? '')),
                        __('cheques fields endorsed_to') => $cheque->endorsedTo?->name,
                        __('cheques fields description') => $cheque->desc,
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
                    <h2 class="card-title text-base">{{ __('cheques actions') }}</h2>
                    <div class="grid gap-4 lg:grid-cols-2">
                        @foreach ($cheque->availableActions() as $action)
                            <form method="POST" action="{{ route('cheques.transition', [$cheque, $action]) }}" class="rounded-box border border-base-300 p-4">
                                @csrf
                                <input type="hidden" name="version" value="{{ $cheque->version }}">
                                <h3 class="font-bold mb-3">{{ __('cheques action ' . $action) }}</h3>
                                @if ($action === 'deposit')
                                    <select name="bank_account_id" class="select select-bordered select-sm w-full" required>
                                        <option value="">{{ __('cheques select_bank_account') }}</option>
                                        @foreach ($bankAccounts as $account)
                                            <option value="{{ $account->id }}">{{ $account->bank?->name }} — {{ $account->name }}</option>
                                        @endforeach
                                    </select>
                                @elseif($action === 'endorse')
                                    <select name="party_id" class="select select-bordered select-sm w-full" required>
                                        <option value="">{{ __('cheques select_endorsee') }}</option>
                                        @foreach ($parties as $party)
                                            <option value="{{ $party->id }}">{{ $party->name }}</option>
                                        @endforeach
                                    </select>
                                @endif
                                <div class="grid grid-cols-2 gap-2 mt-2">
                                    <x-date-picker name="date" :id="'date_' . $action" :value="toEnglish(formatDate(now()))" :placeholder="__('cheques action_date')" />
                                    <input name="description" class="input input-bordered input-sm" placeholder="{{ __('cheques action_note') }}">
                                </div>
                                <button class="btn btn-primary btn-sm mt-3" onclick="return confirm('{{ __('cheques confirm_action') }}')">{{ __('cheques action ' . $action) }}</button>
                            </form>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <div class="flex justify-between">
                    <h2 class="card-title text-base">{{ __('cheques timeline') }}</h2>
                    @if ($cheque->histories->whereNull('reverted_at')->whereNotNull('from_status')->isNotEmpty())
                        <form method="POST" action="{{ route('cheques.transition', [$cheque, 'revert']) }}">
                            @csrf
                            <input type="hidden" name="version" value="{{ $cheque->version }}">
                            <button class="btn btn-warning btn-xs" onclick="return confirm('{{ __('cheques confirm_revert') }}')">{{ __('cheques revert_latest') }}</button>
                        </form>
                    @endif
                </div>
                <div class="mt-4 space-y-3">
                    @foreach ($cheque->histories->reverse() as $history)
                        <div class="border-s-4 {{ $history->reverted_at ? 'border-base-300 opacity-50' : 'border-primary' }} ps-4 py-1">
                            <div class="flex flex-wrap gap-2">
                                <strong>{{ __('cheques event ' . $history->event) }}</strong>
                                <span>{{ formatDateTime($history->occurred_at) }}</span>
                                @if ($history->reverted_at)
                                    <span class="badge badge-ghost badge-sm">{{ __('cheques reverted') }}</span>
                                @endif
                            </div>
                            <div class="text-sm opacity-70">{{ $history->actor?->name }}
                                @if ($history->document_id)
                                    <a class="link" href="{{ route('documents.show', $history->document_id) }}">{{ __('cheques document') }}</a>
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
