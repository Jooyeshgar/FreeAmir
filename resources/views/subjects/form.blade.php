<div class="grid grid-cols-3 gap-6">
    <div class="col-span-2 md:col-span-1">
        <x-input name="name" id="name" title="{{ __('Name') }}" :value="old('name', $subject->name ?? '')" placeholder="{{ __('Please enter the name') }}" />
    </div>
    <div class="col-span-2 md:col-span-1">
        <x-select title="{{ __('Type') }}" name="type" id="type" :options="[
            'debtor' => __('Debtor'),
            'creditor' => __('Creditor'),
            'both' => __('Both'),
        ]" :selected="old('type', $subject->type ?? $parentSubject?->type ?? 'both')" />
    </div>

    @if (! $parentSubject)
        <div class="col-span-2 md:col-span-1">
            <x-select title="{{ __('Permanent/Temporary') }}" name="is_permanent" id="is_permanent" :options="[
                '1' => __('Permanent'),
                '0' => __('Temporary'),
            ]" :selected="old('is_permanent', $subject->is_permanent ?? 0)" />
        </div>
    @endif

    <div class="col-span-2 md:col-span-1">
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
            <x-input name="parent_name" id="parent_name" title="{{ __('Subject') }}" :value="$parentSubject->name ?? __('Main Subject')" disabled />
            <input type="hidden" name="parent_id" value="{{ $parentSubject->id ?? null }}">
        @endif
    </div>

    <div class="col-span-2 md:col-span-1">
        <div class="flex gap-2 items-end">
            <div class="w-2/3">
                <x-input name="subject_code" id="subject_code" title="{{ __('Code') }}" placeholder="{{ __('Code will generate automatically') }}"
                    value="{{ old('subject_code', isset($subject) && $subject->code ? substr($subject->code, -3) : '') }}" />
            </div>
            @if ($parentSubject)
                <div>
                    <div class="px-3 py-2 bg-gray-100 border border-gray-300 rounded-md text-gray-700">
                        {{ $parentSubject->formattedCode() }}
                    </div>
                </div>
            @endif
        </div>
    </div>

</div>
