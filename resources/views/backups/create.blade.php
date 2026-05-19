@php
    use App\Enums\FiscalYearSection;
@endphp

<x-app-layout :title="__('Backup')">
    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <span class="card-title">{{ __('Backup') }}</span>
            <form action="{{ route('backups.export') }}" method="POST" enctype="multipart/form-data"
                x-data="{
                    docFileSizeMb: null,
                    documentsChecked: true,
                    documentFilesChecked: true,
                    get showDepWarning() {
                        return this.documentFilesChecked && !this.documentsChecked;
                    },
                    fetchSize() {
                        const sourceId = document.getElementById('source_id').value;
                        if (!sourceId || !this.documentFilesChecked) {
                            this.docFileSizeMb = null;
                            return;
                        }
                        fetch('{{ route('backups.document-files-size') }}?source_id=' + sourceId)
                            .then(r => r.json())
                            .then(data => { this.docFileSizeMb = data.size_mb; })
                            .catch(() => { this.docFileSizeMb = null; });
                    },
                    handleSubmit(e) {
                        if (this.showDepWarning) {
                            if (!window.confirm('{{ __('Document Files requires Documents to be selected. If you proceed, Document Files will be excluded from the backup. Do you want to continue?') }}')) {
                                e.preventDefault();
                            }
                        }
                    }
                }"
                x-init="fetchSize()"
                x-on:submit="handleSubmit($event)">
                @csrf
                <fieldset id="previousYears" class="grid grid-cols-2 gap-6 p-5 my-3">
                    <div class="form-control">
                        <label for="source_id" class="label">
                            <span class="label-text">{{ __('Backup from') }}</span>
                        </label>
                        <select class="select select-bordered w-full" id="source_id" name="source_id" x-on:change="fetchSize()">
                            @foreach ($previousYears as $year)
                                <option value="{{ $year->id }}" @selected($year->fiscal_year == $currentYear)>
                                    {{ $year->name }} - {{ $year->fiscal_year }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">{{ __('Select Tables to backup') }}</span>
                        </label>
                        <div class="overflow-x-auto">
                            <table class="table w-full">
                                <thead>
                                    <tr>
                                        <th>{{ __('Select') }}</th>
                                        <th>{{ __('Table Name') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach (FiscalYearSection::ui() as $key => $value)
                                        @php
                                            $isDocumentFiles = $key === FiscalYearSection::DOCUMENT_FILES->value;
                                            $isDocuments = $key === FiscalYearSection::DOCUMENTS->value;
                                        @endphp

                                        <tr>
                                            <td class="w-1/3 px-4 py-3">
                                                <input type="checkbox" name="tables_to_backup[]" value="{{ $key }}" id="table_{{ $key }}"
                                                    class="h-4 w-4 rounded border-gray-300 " @checked(true)
                                                    @if ($isDocuments) x-on:change="documentsChecked = $event.target.checked"
                                                    @elseif ($isDocumentFiles) x-on:change="documentFilesChecked = $event.target.checked; fetchSize()"
                                                    @endif
                                                >
                                            </td>

                                            <td class="px-4 py-3">
                                                <div class="flex flex-col gap-1">
                                                    <label for="table_{{ $key }}" class="text-label cursor-pointer">{{ $value }}</label>

                                                    @if ($isDocumentFiles)
                                                        <span x-show="docFileSizeMb !== null" x-cloak class="text-xs text-warning"
                                                            x-text="`{{ __('Selecting this option will increase the backup size by approximately :size MB.') }}`.replace(':size', $store.utils.convertToFarsi(String(docFileSizeMb)))">
                                                        </span>
                                                        <span x-show="showDepWarning" x-cloak class="text-xs text-error">
                                                            {{ __('Document Files will not be exported because Documents is not selected.') }}
                                                        </span>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </fieldset>
                <div class="card-actions">
                    <button type="submit" class="btn btn-pr">{{ __('Create') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
