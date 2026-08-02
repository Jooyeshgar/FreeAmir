<x-app-layout :title="$checkbook->exists ? __('cheques edit_checkbook') : __('cheques create_checkbook')">
    <x-show-message-bags />
    <form class="card bg-base-100" method="POST" action="{{ $checkbook->exists ? route('checkbooks.update', $checkbook) : route('checkbooks.store') }}">
        @csrf
        @if ($checkbook->exists)
            @method('PUT')
        @endif
        <div class="card-body">
            <h1 class="card-title">{{ $checkbook->exists ? __('cheques edit_checkbook') : __('cheques create_checkbook') }}</h1>
            <div class="grid gap-4 md:grid-cols-3">
                <x-select id="bank_account_id" name="bank_account_id" title="{{ __('cheques fields bank_account') }}"
                    :options="$bankAccounts->mapWithKeys(fn ($account) => [$account->id => $account->name])->all()"
                    :selected="old('bank_account_id', $checkbook->bank_account_id)" required />
                <x-text-input label_text_class="text-gray-500" name="title" title="{{ __('cheques fields title') }}" :value="old('title', $checkbook->title)" required />
                <x-text-input label_text_class="text-gray-500" name="serial_prefix" title="{{ __('cheques serial_prefix') }}" :value="old('serial_prefix', $checkbook->serial_prefix)" />
                <x-text-input label_text_class="text-gray-500" name="start_leaf_number" title="{{ __('cheques start_leaf') }}" :value="old('start_leaf_number', $checkbook->start_leaf_number)" type="number" required />
                <x-text-input label_text_class="text-gray-500" name="end_leaf_number" title="{{ __('cheques end_leaf') }}" :value="old('end_leaf_number', $checkbook->end_leaf_number)" type="number" required />
                <x-text-input label_text_class="text-gray-500" name="next_leaf_number" title="{{ __('cheques next_leaf') }}" :value="old('next_leaf_number', $checkbook->next_leaf_number)" type="number" />
                <x-checkbox id="is_active" name="is_active" title="{{ __('cheques active') }}" :checked="old('is_active', $checkbook->exists ? $checkbook->is_active : true)" />
                <input type="hidden" name="is_active" value="0">
            </div>

            <div class="card-actions justify-end">
                <a class="btn btn-ghost" href="{{ route('checkbooks.index') }}">{{ __('Back') }}</a>
                <button class="btn btn-primary">{{ __('cheques save') }}</button>
            </div>
        </div>
    </form>
</x-app-layout>
