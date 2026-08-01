<x-app-layout :title="$cheque ? __('cheques edit') : __('cheques create')">
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl" x-data="{ direction: '{{ old('direction', $cheque?->direction->value ?? $selectedDirection->value) }}', checkbook: '{{ old('checkbook_id', $cheque?->checkbook_id) }}' }">
        <form method="POST" action="{{ $cheque ? route('cheques.update', $cheque) : route('cheques.store') }}" class="card-body">
            @csrf
            @if($cheque)
                @method('PUT')
                <input type="hidden" name="version" value="{{ $cheque->version }}">
            @endif
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="card-title">{{ $cheque ? __('cheques edit') : __('cheques create') }}</h1>
                <a class="btn btn-ghost" href="{{ $cheque ? route('cheques.show', $cheque) : route('cheques.index') }}">{{ __('cheques back') }}</a>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <label class="form-control">
                    <span class="label-text mb-2">{{ __('cheques fields direction') }} *</span>
                    <select name="direction" class="select select-bordered" x-model="direction" required>
                        @foreach (\App\Enums\ChequeType::directions() as $direction)
                            <option value="{{ $direction->value }}" @selected((string) old('direction', $cheque?->direction->value ?? $selectedDirection->value) === (string) $direction->value)>{{ $direction->label() }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-control">
                    <span class="label-text mb-2">{{ __('cheques fields purpose') }} *</span>
                    <select name="purpose" class="select select-bordered" required>
                        @foreach (\App\Enums\ChequeType::purposes() as $purpose)
                            <option value="{{ $purpose->value }}" @selected((string) old('purpose', $cheque?->purpose->value ?? \App\Enums\ChequeType::SETTLEMENT->value) === (string) $purpose->value)>{{ $purpose->label() }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-control">
                    <span class="label-text mb-2">{{ __('cheques fields party') }} *</span>
                    <select name="party_id" class="select select-bordered" required>
                        <option value="">{{ __('cheques select') }}</option>
                        @foreach ($parties as $party)
                            <option value="{{ $party->id }}" @selected((string) old('party_id', $cheque?->customer_id) === (string) $party->id)>{{ $party->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-control">
                    <span class="label-text mb-2">{{ __('cheques fields amount') }} *</span>
                    <input name="amount" value="{{ old('amount', $cheque?->amount) }}" class="input input-bordered" inputmode="decimal" required>
                </label>
                <x-date-picker name="issue_date" id="issue_date" :title="__('cheques fields issue_date')" :value="old('issue_date', $cheque ? toEnglish(formatDate($cheque->write_date)) : null)" required />
                <x-date-picker name="due_date" id="due_date" :title="__('cheques fields due_date')" :value="old('due_date', $cheque ? toEnglish(formatDate($cheque->due_date)) : null)" required />
                <label class="form-control">
                    <span class="label-text mb-2">{{ __('cheques fields sayad') }} *</span>
                    <input name="sayad_number" value="{{ old('sayad_number', $cheque?->sayad_number) }}" class="input input-bordered font-mono" inputmode="numeric" minlength="16" maxlength="16" required>
                </label>
                <label class="form-control" x-show="!checkbook">
                    <span class="label-text mb-2">{{ __('cheques fields serial') }} *</span>
                    <input name="serial" value="{{ old('serial', $cheque?->serial) }}" class="input input-bordered">
                </label>
                <label class="form-control">
                    <span class="label-text mb-2">{{ __('cheques fields bank') }} *</span>
                    <select name="bank_id" class="select select-bordered" required>
                        <option value="">{{ __('cheques select') }}</option>
                        @foreach ($banks as $bank)
                            <option value="{{ $bank->id }}" @selected((string) old('bank_id', $cheque?->bank_id) === (string) $bank->id)>{{ $bank->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-control" x-show="direction === '{{ \App\Enums\ChequeType::PAYABLE->value }}'">
                    <span class="label-text mb-2">{{ __('cheques fields bank_account') }} *</span>
                    <select name="bank_account_id" class="select select-bordered" :required="direction === '{{ \App\Enums\ChequeType::PAYABLE->value }}'">
                        <option value="">{{ __('cheques select') }}</option>
                        @foreach ($bankAccounts as $account)
                            <option value="{{ $account->id }}" @selected((string) old('bank_account_id', $cheque?->bank_account_id) === (string) $account->id)>{{ $account->bank?->name }} — {{ $account->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-control" x-show="direction === '{{ \App\Enums\ChequeType::PAYABLE->value }}'">
                    <span class="label-text mb-2">{{ __('cheques fields checkbook') }}</span>
                    <select name="checkbook_id" class="select select-bordered" x-model="checkbook">
                        <option value="">{{ __('cheques none') }}</option>
                        @foreach ($checkbooks as $item)
                            <option value="{{ $item->id }}" @selected((string) old('checkbook_id', $cheque?->checkbook_id) === (string) $item->id)>{{ $item->title }} ({{ localizeNumber($item->next_leaf_number) }}–{{ localizeNumber($item->end_leaf_number) }})</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-control" x-show="direction === '{{ \App\Enums\ChequeType::PAYABLE->value }}' && checkbook">
                    <span class="label-text mb-2">{{ __('cheques fields leaf_number') }} *</span>
                    <input name="checkbook_leaf_number" value="{{ old('checkbook_leaf_number', $cheque?->checkbook_leaf_number) }}" class="input input-bordered" inputmode="numeric">
                </label>
                <label class="form-control">
                    <span class="label-text mb-2">{{ __('cheques fields branch_name') }}</span>
                    <input name="branch_name" value="{{ old('branch_name', $cheque?->branch_name) }}" class="input input-bordered">
                </label>
                <label class="form-control">
                    <span class="label-text mb-2">{{ __('cheques fields branch_city') }}</span>
                    <input name="branch_city" value="{{ old('branch_city', $cheque?->branch_city) }}" class="input input-bordered">
                </label>
            </div>
            <label class="form-control mt-4">
                <span class="label-text mb-2">{{ __('cheques fields description') }}</span>
                <textarea name="description" class="textarea textarea-bordered" rows="3">{{ old('description', $cheque?->desc) }}</textarea>
            </label>
            <div class="card-actions justify-end mt-4">
                <button class="btn btn-primary">{{ __('cheques save') }}</button>
            </div>
        </form>
    </div>
</x-app-layout>
