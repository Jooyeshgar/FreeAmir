@php
    $selectedDirectionValue = (string) old('direction', $cheque?->direction->value ?? $selectedDirection->value);
    $payableDirectionValue = (string) \App\Enums\ChequeType::PAYABLE->value;
    $directionTitle = $selectedDirectionValue === $payableDirectionValue ? __('Issue cheque') : __('Receive cheque');
    $formTitle = $cheque ? __('Edit cheque') : $directionTitle;
    $selectedBankAccountId = old('bank_account_id', $cheque?->bank_account_id ?? request('bank_account_id'));
    $selectedChequebookId = old('chequebook_id', $cheque?->chequebook_id ?? request('chequebook_id'));
@endphp

<x-app-layout :title="$formTitle">
    <x-show-message-bags />

    {{-- Page Header --}}
    <div class="mx-auto max-w-6xl px-1 pb-6">
        <div class="card overflow-hidden border border-base-200 bg-base-100 shadow-sm">
            <form method="POST" action="{{ $cheque ? route('cheques.update', $cheque) : route('cheques.store') }}">
            @csrf
            @if ($cheque)
                @method('PUT')
            @endif

            <div class="border-b border-base-200 bg-gradient-to-br from-primary/5 via-base-100 to-secondary/5 px-5 py-4 sm:px-6">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="font-bold text-base-content">{{ $formTitle }}</h2>
                </div>
                <p class="mt-1 text-sm text-base-content/45">{{ __('Enter the cheque details.') }}</p>
            </div>

            <div class="p-5 sm:p-6
                [&_fieldset]:min-w-0
                [&_.label]:text-sm [&_.label]:font-medium [&_.label]:text-base-content/70
                [&_.input]:h-10 [&_.input]:min-h-10 [&_.input]:max-h-10
                [&_.input]:rounded-lg [&_.input]:border-base-300 [&_.input]:bg-base-100 [&_.input]:shadow-sm
                [&_.input]:transition [&_.input:focus]:border-primary [&_.input:focus]:outline-none [&_.input:focus]:ring-2 [&_.input:focus]:ring-primary/10
                [&_.select]:h-10 [&_.select]:min-h-10 [&_.select]:max-h-10
                [&_.select]:rounded-lg [&_.select]:border-base-300 [&_.select]:bg-base-100 [&_.select]:shadow-sm
                [&_.select]:transition [&_.select:focus]:border-primary [&_.select:focus]:outline-none [&_.select:focus]:ring-2 [&_.select:focus]:ring-primary/10
                [&_.textarea]:min-h-28 [&_.textarea]:rounded-lg [&_.textarea]:border-base-300 [&_.textarea]:bg-base-100 [&_.textarea]:shadow-sm
                [&_.textarea]:transition [&_.textarea:focus]:border-primary [&_.textarea:focus]:outline-none [&_.textarea:focus]:ring-2 [&_.textarea:focus]:ring-primary/10">
                <div class="grid grid-cols-1 sm:grid-cols-2 items-start gap-x-5 gap-y-4 xl:grid-cols-3" x-data="{
                    direction: @js($selectedDirectionValue),
                    bankAccountId: @js((string) $selectedBankAccountId),
                }">
                    <input type="hidden" name="direction" x-model="direction">

                    <div class="col-span-2 md:col-span-1">
                        <fieldset class="w-full">
                            <label for="purpose" class="label">{{ __('Purpose') }}</label>
                            <select id="purpose" name="purpose" class="select w-full border-slate-400">
                                @foreach (\App\Enums\ChequeType::purposes() as $purpose)
                                    <option value="{{ $purpose->value }}" @selected((string) old('purpose', $cheque?->purpose->value ?? \App\Enums\ChequeType::SETTLEMENT->value) === (string) $purpose->value)>
                                        {{ $purpose->label() }}
                                    </option>
                                @endforeach
                            </select>
                        </fieldset>
                    </div>

                    <x-text-input input_name="title" title="{{ __('Cheque Title') }}"
                        input_value="{{ old('title') ?? ($cheque?->title ?? '') }}" placeholder="{{ __('Cheque Title') }}"
                        label_text_class="text-sm text-gray-500"></x-text-input>

                    <div class="col-span-2 md:col-span-1">
                        @php
                            $initialAccountSideId = old('customer_id', $cheque?->customer_id);
                            $initialAccountSideValue = $initialAccountSideId ? "customer-{$initialAccountSideId}" : '';
                        @endphp
                        <div class="w-full" x-data="{
                            accountSideId: @js((string) $initialAccountSideId),
                            selectedAccountSide: @js($initialAccountSideValue),
                        }">
                            <label class="label">{{ __('Account side') }}</label>
                            <x-select-box :options="[['headerGroup' => 'customer', 'options' => $accountSides]]"
                                x-model="selectedAccountSide"
                                x-init="selectedValue = selectedAccountSide"
                                placeholder="{{ __('Select account side') }}"
                                @selected="accountSideId = $event.detail.id" />
                            <x-input name="customer_id" x-bind:value="accountSideId" hidden />
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
                            label_text_class="text-sm text-gray-500" input_class="datePicker"></x-text-input>
                    </div>

                    <div class="col-span-2 md:col-span-1">
                        <x-text-input data-jdp title="{{ __('Due date') }}" input_name="due_date"
                            id_input="due_date" placeholder="{{ __('Due date') }}" readonly required
                            input_value="{{ old('due_date') ?? ($cheque ? convertToJalali($cheque->due_date, true) : '') }}"
                            label_text_class="text-sm text-gray-500" input_class="datePicker"></x-text-input>
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

                    <div class="col-span-2 rounded-xl border border-primary/20 bg-primary/5 p-4 xl:col-span-3" x-show="direction === @js($payableDirectionValue)"
                        style="{{ $selectedDirectionValue === $payableDirectionValue ? '' : 'display: none;' }}">
                        <div class="mb-3 flex items-center gap-2">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M5 10V8l7-4 7 4v2M5 10v8m4-8v8m6-8v8m4-8v8M3 20h18" />
                                </svg>
                            </span>
                            <h3 class="text-sm font-bold text-base-content">{{ __('Bank account') }} / {{ __('Chequebook') }}</h3>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <fieldset class="w-full">
                                <label for="bank_account_id" class="label">{{ __('Bank account') }}</label>
                                <select id="bank_account_id" name="bank_account_id" x-model="bankAccountId"
                                    class="select w-full border-slate-400"
                                    :required="direction === @js($payableDirectionValue)"
                                    :disabled="direction !== @js($payableDirectionValue)">
                                    <option value="">{{ __('Select bank account') }}</option>
                                    @foreach ($bankAccounts as $account)
                                        <option value="{{ $account->id }}" @selected((string) $selectedBankAccountId === (string) $account->id)>
                                            {{ $account->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </fieldset>

                            <fieldset class="w-full">
                                <label for="chequebook_id" class="label">{{ __('Chequebook') }}</label>
                                <select id="chequebook_id" name="chequebook_id" class="select w-full border-slate-400" :disabled="direction !== @js($payableDirectionValue)">
                                    <option value="">{{ __('No chequebook') }}</option>
                                    @foreach ($chequebooks as $chequebook)
                                        <option value="{{ $chequebook->id }}" @selected((string) $selectedChequebookId === (string) $chequebook->id)>
                                            {{ $chequebook->bankAccount?->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs leading-relaxed break-words whitespace-normal text-base-content/60">{{ __('Selecting a chequebook automatically assigns its next leaf as the cheque number.') }}</p>
                            </fieldset>
                        </div>
                    </div>

                    <div class="col-span-2 xl:col-span-3">
                        <x-textarea name="description" id="description" :title="__('Description')"
                            :value="old('description', $cheque?->desc)" rows="3" maxlength="1000" />
                    </div>
                </div>

                </div>

                <div class="flex flex-wrap items-center justify-end gap-2 border-t border-base-200 bg-base-200/25 px-5 py-4 sm:px-6">
                    <a class="btn btn-ghost" href="{{ $cheque ? route('cheques.show', $cheque) : route('cheques.index') }}">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary min-w-36 gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        {{ $cheque ? __('Save changes') : $directionTitle }}
                    </button>
                </div>
            </form>
        </div>
    </div>
    @pushOnce('scripts')
        <script type="module">
            jalaliDatepicker.startWatch({'persianDigits': true});
        </script>
    @endPushOnce
</x-app-layout>
