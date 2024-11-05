
<div class="grid grid-cols-2 gap-6">
    <div class="col-span-2 md:col-span-1">
        <x-input name="code" id="code" title="{{ __('Code') }}" :value="old('code', $subject->code ?? '')"
            placeholder="{{ __('Please insert the code') }}" />
    </div>

    <div class="col-span-2 md:col-span-1">
        <x-input name="name" id="name" title="{{ __('Name') }}" :value="old('name', $subject->name ?? '')"
            placeholder="{{ __('Please enter the name') }}" />
    </div>
    <div class="col-span-2 md:col-span-1">
        <x-select title="{{ __('Parent') }}" name="parent_id" id="parent_id" :options="$parentSubjects->pluck('name', 'id')" :selected="old('parent_id', $subject->parent_id ?? '')" />
    </div>

    <div class="col-span-2 md:col-span-1">
        <x-select title="{{ __('Type') }}" name="type" id="type" :options="[
            'debtor' => __('Debtor'),
            'creditor' => __('Creditor'),
            'both' => __('Both'),
        ]" :selected="old('type', $subject->type ?? '')" />
   </div>
</div>
