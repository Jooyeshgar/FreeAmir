@php
    use App\Enums\FiscalYearSection;
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Backup') }}
        </h2>
    </x-slot>
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
                }">
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
                                <tbody>
                                    @foreach (FiscalYearSection::ui() as $key => $value)
                                        @php $isDocumentFiles = $value === FiscalYearSection::DOCUMENT_FILES->value; @endphp
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="tables_to_backup[]"
                                                    value="{{ $value }}" id="table_{{ $value }}"
                                                    class="checkbox" @checked(!$isDocumentFiles)
                                                    @if ($isDocumentFiles) x-on:change="fetchSize()" @endif>
                                            </td>
                                            <td>
                                                <label for="table_{{ $value }}"
                                                    class="label-text">{{ $key }}</label>
                                            </td>
                                        </tr>
                                        @if ($isDocumentFiles)
                                            <tr x-show="docFileSizeMb !== null" x-cloak>
                                                <td colspan="2" class="text-sm text-info py-1 px-2"
                                                    x-text="`{{ __('Selecting this option will increase the backup size by approximately :size MB.') }}`.replace(':size', $store.utils.formatNumber(docFileSizeMb))">
                                                </td>
                                            </tr>
                                        @endif
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
