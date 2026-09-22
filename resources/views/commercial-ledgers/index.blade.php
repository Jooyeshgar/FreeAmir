<x-app-layout :title="__('Commercial Ledgers')">
    <div class="flex flex-wrap items-center justify-between gap-3 py-3">
        <div>
            <h1 class="text-xl font-bold text-base-content">{{ __('Commercial Ledgers') }}</h1>
            <p class="text-sm text-base-content/50 mt-0.5">{{ __('Create and manage commercial ledger files for tax reporting.') }}</p>
        </div>
        <button type="button" class="btn btn-sm lg:btn-md btn-primary" onclick="document.getElementById('commercial-ledger-settings').showModal()">
            {{ __('Create Commercial Ledger') }}
        </button>
    </div>

    <x-show-message-bags />

    <x-card class_body="p-4">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('File Type') }}</th>
                        <th>{{ __('From Date') }}</th>
                        <th>{{ __('To Date') }}</th>
                        <th>{{ __('Ledger Seal Tracking Code') }}</th>
                        <th>{{ __('Fiscal Year (Ending)') }}</th>
                        <th>{{ __('Warning Rows') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($exports as $export)
                        <tr>
                            <td><span class="badge badge-sm badge-outline uppercase">{{ $export->format === 'xlsx' ? 'Excel' : 'CSV' }}</span></td>
                            <td>{{ formatDate($export->from_date) }}</td>
                            <td>{{ formatDate($export->to_date) }}</td>
                            <td>{{ $export->seal_tracking_code }}</td>
                            <td>{{ localizeNumber($export->company->fiscal_year) }}</td>
                            <td>
                                <span @class([
                                    'badge badge-sm',
                                    'badge-warning' => $warningRowsCounts[$export->id] > 0,
                                    'badge-ghost' => $warningRowsCounts[$export->id] === 0,
                                ]) title="{{ __('Rows with zero debit and credit') }}">
                                    {{ localizeNumber($warningRowsCounts[$export->id]) }}
                                </span>
                            </td>
                            <td><span class="badge badge-sm badge-success badge-outline whitespace-nowrap">{{ __('Ready to Send') }}</span></td>
                            <td>
                                <div class="inline-flex gap-1">
                                    <a class="btn btn-sm btn-primary" href="{{ route('commercial-ledgers.download', $export) }}">{{ __('Download') }}</a>
                                    <a class="btn btn-sm" href="{{ route('commercial-ledgers.show', $export) }}">{{ __('Preview') }}</a>
                                    <form method="POST" action="{{ route('commercial-ledgers.destroy', $export) }}" onsubmit="return confirm('{{ __('Are you sure you want to delete this commercial ledger?') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-error btn-outline" type="submit">{{ __('Delete') }}</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="py-4 text-center opacity-60">{{ __('No commercial ledger has been generated yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($exports->hasPages())
            <div class="border-t p-2">{{ $exports->links() }}</div>
        @endif
    </x-card>

    <dialog id="commercial-ledger-settings" class="modal">
        <div class="modal-box w-11/12 max-w-3xl overflow-visible">
            <form method="dialog"><button class="btn btn-sm btn-circle btn-ghost absolute left-2 top-2" aria-label="{{ __('Close') }}">✕</button></form>
            <h2 class="text-xl font-bold">{{ __('Create Commercial Ledger') }}</h2>
            <form method="POST" action="{{ route('commercial-ledgers.store') }}" class="mt-5 space-y-4">
                @csrf
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <x-text-input data-jdp title="{{ __('From Date') }}" input_name="from_date" id_input="from_date"
                        placeholder="{{ __('From Date') }}" readonly required
                        input_value="{{ old('from_date') ?? $defaultFromDate }}"
                        label_text_class="text-gray-500 text-nowrap" input_class="datePicker"></x-text-input>
                    <x-text-input data-jdp title="{{ __('To Date') }}" input_name="to_date" id_input="to_date"
                        placeholder="{{ __('To Date') }}" readonly required
                        input_value="{{ old('to_date') ?? $defaultToDate }}"
                        label_text_class="text-gray-500 text-nowrap" input_class="datePicker"></x-text-input>
                </div>
                @if($unapprovedDocumentsCount > 0)
                    <div class="alert alert-warning text-sm">
                        {{ trans_choice(':count document is not approved yet.|:count documents are not approved yet.', $unapprovedDocumentsCount, ['count' => localizeNumber($unapprovedDocumentsCount)]) }}
                    </div>
                @endif
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <fieldset class="form-control w-full">
                        <label class="label" for="format">{{ __('Export Format') }}*</label>
                        <select id="format" name="format" class="select select-bordered w-full" required>
                            <option value="xlsx" @selected(old('format', 'xlsx') === 'xlsx')>{{ __('Excel (XLSX)') }}</option>
                            <option value="csv" @selected(old('format') === 'csv')>{{ __('CSV') }}</option>
                        </select>
                        @error('format')<span class="label text-xs text-error">{{ $message }}</span>@enderror
                    </fieldset>
                    <x-input id="seal_tracking_code" name="seal_tracking_code" :title="__('Ledger Seal Tracking Code')" :value="old('seal_tracking_code')" required />
                </div>
                <fieldset class="form-control w-full">
                    <label class="label" for="ledger_type">{{ __('Ledger Type / Aggregation Level') }}*</label>
                    <select id="ledger_type" name="ledger_type" class="select select-bordered w-full" required>
                        @foreach($ledgerTypes as $type)
                            <option value="{{ $type->value }}" @selected((int) old('ledger_type', $ledgerTypes[0]->value) === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                    @error('ledger_type')<span class="label text-xs text-error">{{ $message }}</span>@enderror
                </fieldset>
                <div class="modal-action">
                    <button type="button" class="btn btn-sm lg:btn-md" onclick="document.getElementById('commercial-ledger-settings').close()">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-sm lg:btn-md btn-primary">{{ __('Create Commercial Ledger') }}</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop"><button>{{ __('Close') }}</button></form>
    </dialog>

    @if($errors->any())
        @push('scripts')
            <script>document.getElementById('commercial-ledger-settings')?.showModal();</script>
        @endpush
    @endif

    @pushOnce('scripts')
        <script type="module">
            jalaliDatepicker.startWatch({persianDigits: true,
                selector: '#commercial-ledger-settings input[data-jdp]',
                container: '#commercial-ledger-settings'});
        </script>
    @endPushOnce

</x-app-layout>
