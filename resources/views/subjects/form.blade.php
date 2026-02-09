<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2">
    <div>
        <x-input name="name" id="name" title="{{ __('Name') }}" :value="old('name', $subject->name ?? '')" placeholder="{{ __('Please enter the name') }}" />
    </div>

    <div class="flex flex-col justify-end" x-data="{ selectedType: @js(old('type', $subject->type ?? 'debtor')) }">
        <span class="label-text">{{ __('Type') }}</span>
        <select name="type" id="type" x-model="selectedType" 
            class="bg-white h-10 min-h-10 border input-bordered w-full rounded-md focus:border-indigo-500 focus:ring-indigo-500 text-gray-900 px-2">
            <option class="label-text" value="debtor">{{ __('Debtor') }}</option>
            <option value="creditor">{{ __('Creditor') }}</option>
            <option value="both">{{ __('Both') }}</option>
        </select>
    </div>

    @if (! $parentSubject)
        <div class="flex flex-col justify-end" x-data="{ selectedIsPermanent: @js(old('is_permanent', $subject->is_permanent ?? 0)) }">
            <span class="label-text">{{ __('Permanent/Temporary') }}</span>
            <select name="is_permanent" id="is_permanent" x-model="selectedIsPermanent"
                class="bg-white h-10 min-h-10 border input-bordered w-full rounded-md focus:border-indigo-500 focus:ring-indigo-500 text-gray-900 px-2">
                <option value="1">{{ __('Permanent') }}</option>
                <option value="0">{{ __('Temporary') }}</option>
            </select>
        </div>
    @endif

    @if (isset($subject) && $subject->parent_id)
        <div x-data="{
            selectedName: @js($parentSubject?->name ?? ''),
            selectedCode: @js($parentSubject?->code ?? ''),
            selectedId: @js(old('parent_id', $subject->parent_id)),
        }">
            <x-subject-select url="{{ route('subjects.search') }}" :subjects="$subjects" title="{{ __('Parent Subject') }}" 
                placeholder="{{ __('Select a subject') }}"
                @selected="
                    selectedName = $event.detail.name;
                    selectedCode = $event.detail.code;
                    selectedId = $event.detail.id;
                " />
            <input type="hidden" name="parent_id" x-bind:value="selectedId">
        </div>
    @else
        <div>
            <x-input name="parent_name" id="parent_name" title="{{ __('Subject') }}" :value="$parentSubject->name ?? __('Main Subject')" disabled />
            <input type="hidden" name="parent_id" value="{{ $parentSubject->id ?? null }}">
        </div>
    @endif

    <div class="flex gap-2 items-end">
        <x-input name="subject_code" id="subject_code"
            title="{{ __('Code') }}"
            placeholder="{{ __('Code will generate automatically') }}"
            value="{{ old('subject_code', isset($subject) && $subject->code ? substr($subject->code, -3) : '') }}" />

        @if ($parentSubject)
            <span
                class="px-3 py-2 bg-gray-100 border border-gray-300 rounded-md text-gray-700">
                {{ $parentSubject->formattedCode() }}
            </span>
        @endif
    </div>
</div>
