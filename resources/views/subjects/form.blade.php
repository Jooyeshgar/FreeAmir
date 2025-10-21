<div class="grid grid-cols-2 gap-6">
    <div class="col-span-2 md:col-span-1">
        <x-input name="name" id="name" title="{{ __('Name') }}" :value="old('name', $subject->name ?? '')" placeholder="{{ __('Please enter the name') }}" />
    </div>
    <div class="col-span-2 md:col-span-1">
        <x-select title="{{ __('Type') }}" name="type" id="type" :options="[
            'debtor' => __('Debtor'),
            'creditor' => __('Creditor'),
            'both' => __('Both'),
        ]" :selected="old('type', $subject->type ?? 'both')" />
    </div>
    <div class="col-span-2 md:col-span-1">
        @if (isset($subject))
            <x-subject-select-box :subjects="$subjects" name="parent_id" id_field="parent_id" title="{{ __('Parent Subject') }}" allSelectable="true" :selected="old('parent_id', $subject->parent_id)"
                :exclude-id="$subject->id" />
        @else
            <x-input name="parent_name" id="parent_name" title="{{ __('Subject') }}" value="{{ $parentSubject->name ?? __('Main Subject') }}" disabled />
            <input type="hidden" name="parent_id" value="{{ $parentSubject->id ?? null }}">
        @endif
    </div>
    <div class="col-span-2 md:col-span-1">
        <div class="flex gap-2 items-end">
            <div>
                <x-input name="code" id="code" title="{{ __('Code') }}" placeholder="{{ __('Code will generate automatically') }}"
                    value="{{ old('code', isset($subject) && $subject->code ? substr($subject->code, -3) : '') }}" />
            </div>
            <div>
                <div class="px-3 py-2 bg-gray-100 border border-gray-300 rounded-md text-gray-700">
                    {{ $parentSubject->formattedCode() }}
                </div>
            </div>
        </div>
    </div>

</div>
