<div>
    <fieldset id="subjectForm" class="grid grid-cols-2 gap-6 border p-5 my-3">
        <div class="col-span-2 md:col-span-1">
            <div class="flex gap-4" x-data="{
                                selectedName: '',
                                selectedCode: '',
                                selectedId: '',
                            }">
                <div class="w-1/3 hidden">
                    <x-input name="{{ $config->key }}" id="{{ $config->key }}" placeholder="{{ __('Select Subject Code') }}"
                        title="{{ __('Subject Code') }}" x-bind:value="$store.utils.formatCode(selectedCode)">
                    </x-input>
                </div>
                <x-subject-select-box class="w-2/3" :subjects="$subjects" title="{{ $config->desc }}"
                    id_field="{{ $config->key }}"
                    placeholder="{{ $subjects->where('id', config('amir.'.$config->key))->first()?->name ?? __('Select a subject') }}"
                    allSelectable="true"></x-subject-select-box>
            </div>
        </div>
    </fieldset>
</div>