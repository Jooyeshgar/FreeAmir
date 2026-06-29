<x-app-layout :title="__('Transfer Transaction Between Subjects')">
    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('subjects.transfer') }}" method="POST">
            @csrf
            <div class="card-body">
                <div class="card-title">{{ __('Transfer Transaction Between Subjects') }}</div>
                <x-show-message-bags />

                <div x-data="{ createNewSubject: false }">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div x-data="{
                            selectedName: '',
                            selectedCode: '',
                            selectedId: '',
                        }">
                            <x-subject-select :subjects="$subjects" title="{{ __('Source Subject') }}" placeholder="{{ __('Select source subject') }}"
                                @selected="
                                    selectedName = $event.detail.name;
                                    selectedCode = $event.detail.code;
                                    selectedId = $event.detail.id;
                                " />
                            <x-input name="source_subject_id" x-bind:value="selectedId" hidden />
                        </div>

                        <template x-if="!createNewSubject">
                            <div x-data="{
                                selectedName: '',
                                selectedCode: '',
                                selectedId: '',
                            }">
                                <x-subject-select :subjects="$subjects" title="{{ __('Destination Subject') }}" placeholder="{{ __('Select destination subject') }}"
                                    @selected="
                                        selectedName = $event.detail.name;
                                        selectedCode = $event.detail.code;
                                        selectedId = $event.detail.id;
                                    " />
                                <x-input name="destination_subject_id" x-bind:value="selectedId" hidden />
                            </div>
                        </template>

                        <template x-if="createNewSubject">
                            <div x-data="{
                                selectedName: '',
                                selectedCode: '',
                                selectedId: '',
                            }">
                                <x-subject-select :subjects="$subjects" title="{{ __('Parent Destination Subject') }}" placeholder="{{ __('Select parent destination subject') }}"
                                    @selected="
                                        selectedName = $event.detail.name;
                                        selectedCode = $event.detail.code;
                                        selectedId = $event.detail.id;
                                    " />
                                <x-input name="parent_destination_subject_id" x-bind:value="selectedId" hidden />
                                <p class="text-xs text-gray-500 mt-1">{{ __('A new subject with the same name and properties as the source subject will be created under the selected parent subject.') }}</p>
                            </div>
                        </template>
                    </div>

                    <div class="flex flex-wrap gap-4 mt-6 items-center justify-between">
                        <div class="flex flex-wrap gap-2 items-center">
                            <x-checkbox name="create_new_subject" id="create_new_subject" x-model="createNewSubject" value="1" title="{{ __('Create new subject under parent') }}" />
                            <x-checkbox name="transfer_subjectable" id="transfer_subjectable" value="1" checked title="{{ __('Transfer source subject relation') }}" />
                            <x-checkbox name="remove_source_subject" id="remove_source_subject" value="1" title="{{ __('Remove source subject after transfer') }}" />
                        </div>
                        <div class="flex flex-wrap gap-4 items-center">
                            <a href="{{ route('subjects.index') }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
                            <button id="submitFormPlus" type="submit" name="submit_action" value="create_new" class="btn btn-default rounded-md">
                                {{ __('save and transfer another subject') }}
                            </button>
                            <button type="submit" class="btn btn-primary">{{ __('Transfer') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
