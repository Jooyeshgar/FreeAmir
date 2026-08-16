<x-app-layout :title="__('Journal Report')">
    <div class="font-bold text-gray-500 py-6 text-2xl">
        <span>
            {{ __('Journal Report') }}
        </span>
    </div>
    <x-show-message-bags />

    <form id="journal-report-form" action="{{ route('reports.result') }}" method="get">
        <x-card>
            @include('reports.form', ['type' => 'Journal'])

            <x-input name="columns_selected" value="1" hidden />
            @foreach(['subject_root_code', 'subject_moein_code', 'transaction_desc'] as $col)
                <x-input name="columns[]" :value="$col" hidden />
            @endforeach
        </x-card>
        <div class="mt-2 flex gap-2 justify-end">
            <x-export-delivery-choice id="journal-report-delivery" form="journal-report-form" export="accounting_report_csv" class="btn" />
            <button type="submit" name="action" value="print" formnovalidate class="btn"> {{ __('Print') }}</button>
            <button type="submit" name="action" value="preview" formnovalidate class="btn text-white btn-primary rounded-md"> {{ __('Preview') }}</button>
        </div>
    </form>
</x-app-layout>
