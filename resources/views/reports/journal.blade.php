<x-app-layout :title="__('Create Transaction')">
    <div class="font-bold text-gray-500 py-6 text-2xl">
        <span>
            {{ __('Journal Report') }}
        </span>
    </div>
    <x-show-message-bags />

    <form x-data="{
            exportCsv(exportUrl) {
                const url = new URL(exportUrl);
                ['start_date', 'end_date', 'start_document_number', 'end_document_number'].forEach(name => {
                    const el = this.$root.querySelector('[name=' + name + ']');
                    if (el && el.value) url.searchParams.set(name, el.value);
                });
                const search = this.$root.querySelector('[name=search]');
                if (search && search.value) url.searchParams.set('text', search.value);
                const colInputs = [...this.$root.querySelectorAll('[name=\'columns[]\']')];
                if (colInputs.length) {
                    url.searchParams.set('columns_selected', '1');
                    colInputs.forEach(input => url.searchParams.append('columns[]', input.value));
                }
                window.location.href = url.toString();
            }
        }" action="{{ route('reports.result') }}" method="get">
        <x-card>
            @include('reports.form', ['type' => 'Journal'])

            <x-input name="columns_selected" value="1" hidden />
            @foreach(['subject_root_code', 'subject_moein_code', 'transaction_desc'] as $col)
                <x-input name="columns[]" :value="$col" hidden />
            @endforeach
        </x-card>
        <div class="mt-2 flex gap-2 justify-end">
            <button type="button" @click="exportCsv('{{ route('documents.export') }}')" class="btn">{{ __('Convert to CSV') }}</button>
            <button type="submit" name="action" value="print" class="btn"> {{ __('Print') }}</button>
            <button type="submit" name="action" value="preview" class="btn text-white btn-primary rounded-md"> {{ __('Preview') }}</button>
        </div>
    </form>
</x-app-layout>
