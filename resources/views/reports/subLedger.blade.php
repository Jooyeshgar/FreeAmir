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
                <x-subject-select class="w-2/3" url="{{ route('subjects.search') }}" :subjects="$subjects"
                    title="{{ __('Subject name') }}" placeholder="{{ __('Select a subject') }}"
                    @selected="
                        selectedName = $event.detail.name;
                        selectedCode = $event.detail.code;
                        selectedId = $event.detail.id;
                    " />
                <input type="hidden" name="subject_id" x-bind:value="selectedId">
                <input type="hidden" name="code" x-bind:value="selectedCode">
            </div>

            @include('reports.form', ['type' => 'subLedger'])
        </x-card>
        <div class="mt-2 flex gap-2 justify-end">
            <button type="submit" name="action" value="export_csv" class="btn btn-default rounded-md">
                {{ __('Convert to CSV') }}
            </button>
            <button type="submit" name="action" value="print" class="btn btn-default rounded-md"> {{ __('Print') }}</button>
            <button type="submit" name="action" value="preview" class="btn text-white btn-primary rounded-md"> {{ __('Preview') }}</button>
        </div>
    </form>
</x-app-layout>
