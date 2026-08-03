<x-app-layout :title="$chequebook ? __('Edit chequebook') : __('Create chequebook')">
    <x-show-message-bags />

    <div class="card bg-base-100">
        <form method="POST" action="{{ $chequebook ? route('cheques.chequebooks.update', $chequebook) : route('cheques.chequebooks.store') }}">
            @csrf
            @if ($chequebook)
                @method('PUT')
            @endif

            <div class="card-body">
                <h1 class="card-title">{{ $chequebook ? __('Edit chequebook') : __('Create chequebook') }}</h1>

                <div class="grid gap-5 md:grid-cols-2">
                    <fieldset class="w-full">
                        <label for="bank_account_id" class="label">{{ __('Bank account') }}</label>
                        <select id="bank_account_id" name="bank_account_id" class="select w-full @error('bank_account_id') select-error @enderror" required>
                            <option value="">{{ __('Select…') }}</option>
                            @foreach ($bankAccounts as $account)
                                <option value="{{ $account->id }}" @selected((string) old('bank_account_id', $chequebook?->bank_account_id) === (string) $account->id)>
                                    {{ $account->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('bank_account_id')
                            <span class="label text-xs text-error">{{ $message }}</span>
                        @enderror
                    </fieldset>

                    <x-input name="serial_prefix" id="serial_prefix" :title="__('Serial prefix')" :value="old('serial_prefix', $chequebook?->serial_prefix)"/>
                    <x-input name="first_leaf" id="first_leaf" :title="__('First leaf')" :value="old('first_leaf', $chequebook?->first_leaf)" inputmode="numeric" />
                    <x-input name="last_leaf" id="last_leaf" :title="__('Last leaf')" :value="old('last_leaf', $chequebook?->last_leaf)" inputmode="numeric" />
                    <x-input name="next_leaf" id="next_leaf" :title="__('Next leaf')" :value="old('next_leaf', $chequebook?->next_leaf)" inputmode="numeric" />
                    <div class="md:col-span-2">
                        <x-textarea name="description" id="description" :title="__('Description')" :value="old('description', $chequebook?->desc)" rows="3" maxlength="1000" />
                    </div>
                </div>

                <div class="card-actions justify-end">
                    <a href="{{ $chequebook ? route('cheques.chequebooks.show', $chequebook) : route('cheques.chequebooks.index') }}" class="btn btn-ghost">{{ __('Back') }}</a>
                    <button class="btn btn-primary">{{ __('Save') }}</button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
