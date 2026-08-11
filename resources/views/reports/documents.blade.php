<x-app-layout :title="__('Documents Report')">
    <div class="font-bold text-gray-500 py-6 text-2xl">
        <span>
            {{ __('Documents Report') }}
        </span>
    </div>
    <x-show-message-bags />

    <form id="documents-report-form" action="{{ route('reports.result') }}" method="get">
        <x-card>
            @include('reports.form', ['type' => 'Document'])
        </x-card>
        <div class="mt-2 flex gap-2 justify-end">
            <x-form-export-delivery-choice id="documents-report-delivery" form="documents-report-form" export="accounting_report_csv" class="btn btn-default rounded-md" />
            <button type="submit" name="action" value="print" formnovalidate class="btn btn-default rounded-md"> {{ __('Print') }}</button>
            <button type="submit" name="action" value="preview" formnovalidate class="btn text-white btn-primary rounded-md"> {{ __('Preview') }}</button>
        </div>
    </form>
</x-app-layout>
