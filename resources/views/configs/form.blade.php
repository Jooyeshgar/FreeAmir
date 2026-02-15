<div>
    <fieldset id="subjectForm" class="grid grid-cols-2 gap-6 border p-5 my-3">
        <div class="col-span-2 md:col-span-1">
            <div class="flex gap-4" x-data="{
                selectedName: @js($selectedSubject?->name ?? ''),
                selectedCode: @js($selectedSubject?->code ?? ''),
                selectedId: @js($selectedSubject?->id ?? ''),
            }">
                <input type="hidden" name="key" value="{{ $config->key }}">
                <div class="w-1/3 hidden">
                    <x-input name="{{ $config->key }}" id="{{ $config->key }}" placeholder="{{ __('Select Subject Code') }}" title="{{ __('Subject Code') }}"
                        x-bind:value="$store.utils.formatCode(selectedCode)">
                    </x-input>
                </div>
                <x-subject-select class="w-2/3" :subjects="$subjects" title="{{ $config->desc }}" placeholder="{{ __('Select a subject') }}"
                    @selected="
                        selectedName = $event.detail.name;
                        selectedCode = $event.detail.code;
                        selectedId = $event.detail.id;
                    " />
                <input type="hidden" name="code" x-bind:value="selectedCode">
                <input type="hidden" name="{{ $config->key }}" x-bind:value="selectedId">
            </div>
        </div>
    </fieldset>
</div>
