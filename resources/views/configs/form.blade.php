<div>
    <fieldset id="subjectForm" class="grid grid-cols-2 gap-6 border p-5 my-3">
        <div class="col-span-2 md:col-span-1">
            <div class="flex gap-4" x-data="{
                selectedName: @js($selectedSubject?->name ?? ''),
                selectedCode: @js($selectedSubject?->code ?? ''),
                selectedId: @js($selectedSubject?->id ?? ''),
            }">
                <x-input name="key" value="{{ $config->key }}" hidden />
                <x-input name="{{ $config->key }}" id="{{ $config->key }}" placeholder="{{ __('Select Subject Code') }}" title="{{ __('Subject Code') }}"
                    x-bind:value="$store.utils.formatCode(selectedCode)" hidden />
                <x-subject-select class="w-2/3" :subjects="$subjects" title="{{ $config->desc }}" placeholder="{{ __('Select a subject') }}"
                    @selected="
                        selectedName = $event.detail.name;
                        selectedCode = $event.detail.code;
                        selectedId = $event.detail.id;
                    " />
                <x-input name="code" x-bind:value="selectedCode" hidden />
                <x-input name="{{ $config->key }}" x-bind:value="selectedId" hidden />
            </div>
        </div>
    </fieldset>
</div>
