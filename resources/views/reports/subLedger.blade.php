<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Subsidiary Ledger Report') }}
        </h2>
    </x-slot>
    <div class="font-bold text-gray-600 py-6 text-2xl">
        <span>
            {{ __('Subsidiary Ledger Report') }}
        </span>
    </div>
    <x-show-message-bags />

    <form action="{{ route('reports.result') }}" method="get" x-data="{ subjectCode: '' }">
        <x-card>
            <div class="flex gap-4" x-data="{
                selectedName: '',
                selectedCode: '',
                selectedId: '',
            }">
                <div class="w-1/3">
                    <x-input name="code_input" id="code_input" placeholder="{{ __('Subject Code') }}" title="{{ __('Subject') }}"
                        x-bind:value="$store.utils.formatCode(selectedCode)">
                    </x-input>
                </div>
                <x-subject-select-box class="w-2/3" :subjects="$subjects" title="{{ __('Subject name') }}" id_field="subject_id" placeholder="{{ __('Select a subject') }}"
                    allSelectable="true"></x-subject-select-box>
            </div>

            @include('reports.form', ['type' => 'subLedger'])
        </x-card>
        <div class="mt-2 flex gap-2 justify-end">
            <button type="submit" name="export" value="csv" class="btn btn-default rounded-md">
                {{ __('Convert to CSV') }}
            </button>
            <button type="submit" class="btn btn-default rounded-md"> {{ __('Print') }}</button>
            <button type="submit" class="btn text-white btn-primary rounded-md"> {{ __('Preview') }}</button>
        </div>
    </form>
</x-app-layout>
