<div class="grid grid-cols-2 gap-6">
    <div class="col-span-2 md:col-span-1">
        <x-input name="name" id="name" title="{{ __('Name') }}" :value="old('name', $subject->name ?? '')"
            placeholder="{{ __('Please enter the name') }}" />
    </div>
    <div class="col-span-2 md:col-span-1">
        <x-select title="{{ __('Type') }}" name="type" id="type" :options="[
            'debtor' => __('Debtor'),
            'creditor' => __('Creditor'),
            'both' => __('Both'),
        ]" :selected="old('type', $subject->type ?? '')" />
    </div>
    <div class="col-span-2 md:col-span-1">
        <x-input name="parent_name" id="parent_name" title="{{ __('Subject') }}"
            value="{{ $parentSubject->name ?? __('Main Subject') }}" disabled />
        <input type="hidden" name="parent_id" value="{{ $parentSubject->id ?? null }}">
    </div>
    @if (!empty($subject))
        <div class="col-span-2 md:col-span-1">
            <x-input name="code" id="code" title="{{ __('Code') }}" :value="old('code', $subject->code ?? '')"
                placeholder="{{ __('Please insert the code') }}"  hint="{{ __('Code should be unique') }}" />
        </div>
    @else
        <div class="col-span-2 md:col-span-1">
            <x-input name="auto_code" id="auto_code" title="{{ __('Code') }}"
                placeholder="{{ __('Code will generate automatically') }}" disabled />
        </div>
    @endif
</div>
