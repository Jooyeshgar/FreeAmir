@php
    $formTitle = $cheque ? __('Edit cheque') : __('Register cheque');
    $selectedDirectionValue = (string) old('direction', $cheque?->direction->value ?? $selectedDirection->value);
    $payableDirectionValue = (string) \App\Enums\ChequeType::PAYABLE->value;
@endphp

<x-app-layout :title="$formTitle">
    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <form method="POST" action="{{ $cheque ? route('cheques.update', $cheque) : route('cheques.store') }}">
            @csrf
            @if ($cheque)
                @method('PUT')
            @endif

            <div class="card-body">
                <h1 class="card-title">{{ $formTitle }}</h1>

                <div class="grid grid-cols-2 gap-6" x-data="{
                    direction: @js($selectedDirectionValue),
                    bankAccountId: @js((string) old('bank_account_id', $cheque?->bank_account_id)),
                }">
                    <div class="col-span-2 md:col-span-1">
                        <fieldset class="w-full">
                            <label for="direction" class="label">{{ __('Direction') }}</label>
                            <select id="direction" name="direction" x-model="direction"
                                class="select w-full @error('direction') select-error @enderror">
                                @foreach (\App\Enums\ChequeType::directions() as $direction)
                                    <option value="{{ $direction->value }}" @selected($selectedDirectionValue === (string) $direction->value)>
                                        {{ $direction->label() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('direction')
                                <span class="label text-xs text-rose-700">{{ $message }}</span>
                            @enderror
                        </fieldset>
                    </div>

                    <div class="col-span-2 md:col-span-1">
                        <fieldset class="w-full">
                            <label for="purpose" class="label">{{ __('Purpose') }}</label>
                            <select id="purpose" name="purpose" class="select w-full @error('purpose') select-error @enderror">
                                @foreach (\App\Enums\ChequeType::purposes() as $purpose)
                                    <option value="{{ $purpose->value }}" @selected((string) old('purpose', $cheque?->purpose->value ?? \App\Enums\ChequeType::SETTLEMENT->value) === (string) $purpose->value)>
                                        {{ $purpose->label() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('purpose')
                                <span class="label text-xs text-rose-700">{{ $message }}</span>
                            @enderror
                        </fieldset>
                    </div>

                    <div class="col-span-2 md:col-span-1">
                        @php
                            $initialAccountSideId = old('account_side_id', $cheque?->customer_id);
                            $initialAccountSideValue = $initialAccountSideId ? "customer-{$initialAccountSideId}" : '';
                        @endphp
                        <div class="w-full" x-data="{
                            accountSideId: @js((string) $initialAccountSideId),
                            selectedAccountSide: @js($initialAccountSideValue),
                        }">
                            <label class="label">{{ __('Account side') }}</label>
                            <x-select-box :options="[['headerGroup' => 'customer', 'options' => $accountSides]]"
                                x-model="selectedAccountSide"
                                placeholder="{{ __('Select account side') }}"
                                @selected="accountSideId = $event.detail.id" />
                            <x-input name="account_side_id" x-bind:value="accountSideId" hidden />
                            @error('account_side_id')
                                <span class="label text-xs text-rose-700">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div class="col-span-2 md:col-span-1">
                        <x-input name="amount" id="amount" :title="__('Amount')" :value="old('amount', $cheque?->amount)"
                            inputmode="decimal" />
                    </div>

                    <div class="col-span-2 md:col-span-1">
                        <x-text-input data-jdp title="{{ __('Issue date') }}" input_name="issue_date"
                            id_input="issue_date" placeholder="{{ __('Issue date') }}" readonly required
                            input_value="{{ old('issue_date') ?? convertToJalali($cheque?->write_date ?? now(), true) }}"
                            label_text_class="text-gray-500 text-nowrap" input_class="datePicker"></x-text-input>
                        @error('issue_date')
                            <span class="label text-xs text-rose-700">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="col-span-2 md:col-span-1">
                        <x-text-input data-jdp title="{{ __('Due date') }}" input_name="due_date"
                            id_input="due_date" placeholder="{{ __('Due date') }}" readonly required
                            input_value="{{ old('due_date') ?? ($cheque ? convertToJalali($cheque->due_date, true) : '') }}"
                            label_text_class="text-gray-500 text-nowrap" input_class="datePicker"></x-text-input>
                        @error('due_date')
                            <span class="label text-xs text-rose-700">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="col-span-2 md:col-span-1">
                        <x-input name="sayad_number" id="sayad_number" :title="__('16-digit Sayad number')"
                            :value="old('sayad_number', $cheque?->sayad_number)" inputmode="numeric" minlength="16"
                            maxlength="16" autocomplete="off" />
                    </div>

                    <div class="col-span-2 md:col-span-1">
                        <x-input name="cheque_number" id="cheque_number" :title="__('Cheque number')"
                            :value="old('cheque_number', $cheque?->cheque_number)" maxlength="50" autocomplete="off" />
                    </div>

                    <div class="col-span-2 md:col-span-1">
                        <x-input name="serial" id="serial" :title="__('Cheque serial')"
                            :value="old('serial', $cheque?->serial)" maxlength="50" autocomplete="off" />
                    </div>

                    <div class="col-span-2 md:col-span-1" x-show="direction === @js($payableDirectionValue)"
                        style="{{ $selectedDirectionValue === $payableDirectionValue ? '' : 'display: none;' }}">
                        <fieldset class="w-full">
                            <label for="bank_account_id" class="label">{{ __('Bank account') }}</label>
                            <select id="bank_account_id" name="bank_account_id" x-model="bankAccountId"
                                class="select w-full @error('bank_account_id') select-error @enderror"
                                :required="direction === @js($payableDirectionValue)"
                                :disabled="direction !== @js($payableDirectionValue)">
                                <option value="">{{ __('Select bank account') }}</option>
                                @foreach ($bankAccounts as $account)
                                    <option value="{{ $account->id }}" @selected((string) old('bank_account_id', $cheque?->bank_account_id) === (string) $account->id)>
                                        {{ $account->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('bank_account_id')
                                <span class="label text-xs text-rose-700">{{ $message }}</span>
                            @enderror
                        </fieldset>
                    </div>

                    <div class="col-span-2 md:col-span-1" x-show="direction === @js($payableDirectionValue)" style="{{ $selectedDirectionValue === $payableDirectionValue ? '' : 'display: none;' }}">
                        <fieldset class="w-full">
                            <label for="chequebook_id" class="label">{{ __('Chequebook') }}</label>
                            <select id="chequebook_id" name="chequebook_id" class="select w-full @error('chequebook_id') select-error @enderror" :disabled="direction !== @js($payableDirectionValue)">
                                <option value="">{{ __('No chequebook') }}</option>
                                @foreach ($chequebooks as $chequebook)
                                    <option value="{{ $chequebook->id }}" @selected((string) old('chequebook_id', $cheque?->chequebook_id) === (string) $chequebook->id)>
                                        {{ $chequebook->displayName() }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="label text-xs opacity-60">{{ __('Optional for payable cheques') }}</div>
                            @error('chequebook_id')
                                <span class="label text-xs text-rose-700">{{ $message }}</span>
                            @enderror
                        </fieldset>
                    </div>

                    <div class="col-span-2">
                        <x-textarea name="description" id="description" :title="__('Description')"
                            :value="old('description', $cheque?->desc)" rows="3" maxlength="1000" />
                    </div>
                </div>

                <div class="card-actions justify-end">
                    <a class="btn btn-ghost" href="{{ $cheque ? route('cheques.show', $cheque) : route('cheques.index') }}">
                        {{ __('Back') }}
                    </a>
                    <button type="submit" class="btn btn-primary">
                        {{ $cheque ? __('Save changes') : __('Register cheque') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
    @pushOnce('scripts')
        <script type="module">
            jalaliDatepicker.startWatch({'persianDigits': true});
        </script>
    @endPushOnce
</x-app-layout>
