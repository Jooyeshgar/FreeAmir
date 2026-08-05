@php
    $formTitle = $chequebook ? __('Edit chequebook') : __('Create chequebook');
@endphp

<x-app-layout :title="$formTitle">
    <x-show-message-bags />

    {{-- Page Header --}}
    <div class="mx-auto max-w-5xl px-1 pb-6">
        <div class="card overflow-hidden border border-base-200 bg-base-100 shadow-sm">
            <form method="POST" action="{{ $chequebook ? route('chequebooks.update', $chequebook) : route('chequebooks.store') }}">
                @csrf
                @if ($chequebook)
                    @method('PUT')
                @endif

                <div class="border-b border-base-200 bg-gradient-to-br from-primary/5 via-base-100 to-secondary/5 px-5 py-4 sm:px-6">
                    <h2 class="flex gap-2 font-bold text-base-content">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M3.75 7.5h16.5m-16.5 0A2.25 2.25 0 0 1 6 5.25h12a2.25 2.25 0 0 1 2.25 2.25m-16.5 0v9A2.25 2.25 0 0 0 6 18.75h12a2.25 2.25 0 0 0 2.25-2.25v-9M7.5 12h4.5" />
                        </svg>
                        {{ __('Chequebook details') }}
                    </h2>
                    <p class="mt-1 text-sm text-base-content/45">{{ __('Selecting a chequebook automatically assigns its next leaf as the cheque number.') }}</p>
                </div>

                <div class="space-y-6 p-5 sm:p-6 [&_fieldset]:min-w-0 [&_.input]:h-11 [&_.input]:min-h-11 [&_.input]:max-h-11 [&_.select]:h-11 [&_.select]:min-h-11 [&_.select]:max-h-11 [&_.textarea]:min-h-28">
                    {{-- Account and serial --}}
                    <div class="grid items-start gap-x-5 gap-y-4 md:grid-cols-2">
                        <fieldset class="form-control w-full min-w-0">
                            <label for="bank_account_id" class="label">{{ __('Bank account') }}*</label>
                            <select id="bank_account_id" name="bank_account_id"
                                class="select w-full max-w-full border-slate-400 @error('bank_account_id') select-error @enderror" required>
                                <option value="">{{ __('Select bank account') }}</option>
                                @foreach ($bankAccounts as $account)
                                    <option value="{{ $account->id }}" @selected((string) old('bank_account_id', $chequebook?->bank_account_id) === (string) $account->id)>
                                        {{ $account->name }}@if ($account->bank) — {{ $account->bank->name }}@endif
                                    </option>
                                @endforeach
                            </select>
                            @error('bank_account_id')
                                <span class="label text-xs text-error">{{ $message }}</span>
                            @enderror
                        </fieldset>

                        <x-input name="serial_prefix" id="serial_prefix" :title="__('Serial prefix')"
                            :value="old('serial_prefix', $chequebook?->serial_prefix)" maxlength="50" autocomplete="off" />
                    </div>

                    {{-- Leaf range --}}
                    <div class="rounded-xl border border-base-200 bg-base-200/25 p-4 sm:p-5">
                        <div class="mb-3 flex items-center gap-2">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" />
                                </svg>
                            </div>
                            <h3 class="text-sm font-bold text-base-content">{{ __('Serial range') }}</h3>
                        </div>

                        <div class="grid items-start gap-x-5 gap-y-4 sm:grid-cols-3">
                            <x-input name="first_leaf" id="first_leaf" :title="__('First leaf')"
                                :value="old('first_leaf', $chequebook?->first_leaf)" inputmode="numeric" autocomplete="off" :required="true" />
                            <x-input name="last_leaf" id="last_leaf" :title="__('Last leaf')"
                                :value="old('last_leaf', $chequebook?->last_leaf)" inputmode="numeric" autocomplete="off" :required="true" />
                            <x-input name="next_leaf" id="next_leaf" :title="__('Next leaf')"
                                :value="old('next_leaf', $chequebook?->next_leaf)" inputmode="numeric" autocomplete="off" />
                        </div>
                    </div>

                    <x-textarea name="description" id="description" :title="__('Description')"
                        :value="old('description', $chequebook?->desc)" rows="4" maxlength="1000" />
                </div>

                <div class="flex flex-wrap items-center justify-end gap-2 border-t border-base-200 bg-base-200/25 px-5 py-4 sm:px-6">
                    <a href="{{ $chequebook ? route('chequebooks.show', $chequebook) : route('chequebooks.index') }}"
                        class="btn btn-ghost">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary min-w-28 gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        {{ __('Save') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
