@php
    use App\Enums\FiscalYearSection;
@endphp

<x-app-layout :title="__('Backup')">
    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <span class="card-title">{{ __('Backup') }}</span>
            <form action="{{ route('backups.export') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <fieldset id="previousYears" class="grid grid-cols-2 gap-6 p-5 my-3" x-data="{
                    docFileSizeMb: null,
                    fetchSize() {
                        const sourceId = document.getElementById('source_id').value;
                        const checkbox = document.getElementById('table_{{ FiscalYearSection::DOCUMENT_FILES->value }}');
                        if (!sourceId || !checkbox || !checkbox.checked) {
                            this.docFileSizeMb = null;
                            return;
                        }
                        fetch('{{ route('backups.document-files-size') }}?source_id=' + sourceId)
                            .then(r => r.json())
                            .then(data => { this.docFileSizeMb = data.size_mb; })
                            .catch(() => { this.docFileSizeMb = null; });
                    }
                }" x-init="fetchSize()">
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
                                        @php $isDocumentFiles = $key === FiscalYearSection::DOCUMENT_FILES->value; @endphp

                                        <tr>
                                            <td class="w-1/3 px-4 py-3">
                                                <input type="checkbox" name="tables_to_backup[]" value="{{ $key }}" id="table_{{ $key }}"
                                                    class="h-4 w-4 rounded border-gray-300 " @checked(true)
                                                    @if ($isDocumentFiles) x-on:change="fetchSize()" @endif
                                                >
                                            </td>

                                            <td class="px-4 py-3">
                                                <div class="flex flex-col gap-1">
                                                    <label for="table_{{ $key }}" class="text-label cursor-pointer">{{ $value }}</label>

                                                    @if ($isDocumentFiles)
                                                        <span x-show="docFileSizeMb !== null" x-cloak class="text-xs text-warning"
                                                            x-text="`{{ __('Selecting this option will increase the backup size by approximately :size MB.') }}`.replace(':size', $store.utils.convertToFarsi(String(docFileSizeMb)))">
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
