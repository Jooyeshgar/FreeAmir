<x-app-layout>
    <div class="max-w-7xl mx-auto p-4 md:p-6">
        <div class="mb-6">
            <h3 class="text-xl font-bold">{{ __('Export Documents') }}</h3>
        </div>

        <form action="{{ route('documents.export') }}" method="POST" class="space-y-6">
            @csrf
            <x-show-message-bags />

            <div class="card bg-base-100 shadow border border-base-300">
                <div class="card-body">
                    <h2 class="card-title mb-4">{{ __('Filters') }}</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">{{ __('Search by document title or transaction description') }}</span>
                            </label>
                            <x-input name="text" value="{{ old('text', request('text')) }}" class="w-full" />
                        </div>

                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">{{ __('Start date') }}</span>
                            </label>
                            <x-date-picker name="start_date" value="{{ old('start_date', request('start_date')) }}" class="w-full" />
                        </div>

                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">{{ __('End date') }}</span>
                            </label>
                            <x-date-picker name="end_date" value="{{ old('end_date', request('end_date')) }}" class="w-full" />
                        </div>

                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">{{ __('Document start number') }}</span>
                            </label>
                            <x-input type="number" name="start_document_number" value="{{ old('start_document_number', request('start_document_number')) }}" class="w-full" />
                        </div>

                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">{{ __('Document end number') }}</span>
                            </label>
                            <x-input type="number" name="end_document_number" value="{{ old('end_document_number', request('end_document_number')) }}" class="w-full" />
                        </div>
                    </div>
                </div>
            </div>

            <div class="card bg-base-100 shadow border border-base-300" 
                x-data="{ toggleAll(state) { document.querySelectorAll('.export-column-checkbox').forEach(cb => cb.checked = state) } }">
                <div class="card-body">
                    <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-3 mb-4">
                        <h2 class="card-title">{{ __('Select the columns you want to include in the export.') }}</h2>

                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="btn btn-sm btn-outline" @click="toggleAll(true)">{{ __('Select all') }}</button>
                            <button type="button" class="btn btn-sm btn-outline"@click="toggleAll(false)">{{ __('Unselect all') }}</button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                        @foreach ($availableColumns as $key => $label)
                            <label class="flex items-center gap-3 p-3 rounded-lg border border-base-300 cursor-pointer hover:bg-base-200 transition">
                                <input type="checkbox" name="columns[]" value="{{ $key }}" class="checkbox checkbox-primary export-column-checkbox"
                                    {{ in_array($key, old('columns', $defaultColumns)) ? 'checked' : '' }}>
                                <span>{{ __($label) }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row justify-end gap-3">
                <button type="submit" class="btn btn-primary">{{ __('Download CSV') }}</button>
            </div>
        </form>
    </div>

</x-app-layout>