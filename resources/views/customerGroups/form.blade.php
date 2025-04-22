<div class="grid grid-cols-2 gap-6">
    <div class="col-span-2 md:col-span-1">
        <div class="flex overflow-x-auto overflow-y-hidden gap-2 items-center px-4  ">
            <div class="text-sm flex-1 min-w-24 max-w-64 text-center pt-3 ">
                {{ __('Code') }}
            </div>
            <div class="text-sm flex-1 min-w-80 max-w-80 text-center pt-3 ">
                {{ __('chapter title') }}
            </div>
        </div>
        <div class="transaction flex gap-2 items-center px-4 ">

            <div class="flex-1 min-w-24 max-w-64 pb-3">

                <x-text-input disabled input_value="{{ !empty($customerGroup->subject) ? $customerGroup->subject->formattedCode() : '' }}" id="value"
                    label_text_class="text-gray-500" label_class="w-full" input_class="border-white value codeInput "></x-text-input>

            </div>
            <input type="text" class="subject_id" name="subject_id" hidden value="">
            <x-subject-select-box :subjects="$subjects" input_value="{{ $customerGroup->subject->name ?? '' }}" placeholder="{{ __('Select a subject') }}"
                allSelectable="true"></x-subject-select-box>

        </div>
    </div>

    <div class="col-span-2 md:col-span-1">
        <x-input name="name" id="name" title="{{ __('Name') }}" :value="old('name', $customerGroup->name ?? '')" placeholder="{{ __('Please enter name') }}" />
    </div>

    <div class="col-span-2">
        <x-textarea name="description" id="description" title="{{ __('Description') }}" placeholder="{{ __('Please enter the description') }}" :value="old('description', $customerGroup->description ?? '')" />
    </div>

</div>
