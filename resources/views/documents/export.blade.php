<x-app-layout :title="__('Export Documents')">
    <div class="card bg-base-100 shadow-xl max-w-2xl mx-auto">
        <form action="{{ route('documents.export.download') }}" method="POST">
            @csrf
            <div class="card-body">
                <h2 class="card-title">{{ __('Export Documents') }}</h2>
                <x-show-message-bags />

                <div class="flex flex-col gap-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <label class="fieldset">
                            <div class="label">
                                <span>{{ __('Status') }}</span>
                            </div>
                            <select name="status" class="select w-full">
                                <option value="all">{{ __('All Documents') }}</option>
                                <option value="approved" @selected(old('status') === 'approved')>{{ __('Approved') }}</option>
                                <option value="unapproved" @selected(old('status') === 'unapproved')>{{ __('Not approved') }}</option>
                            </select>
                        </label>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="fieldset w-full">
                                <div class="label">
                                    <span>{{ __('From Document Number') }}</span>
                                </div>
                                <x-input name="start_document_number" type="number" value="{{ old('start_document_number') }}" />
                            </label>
                        </div>

                        <div>
                            <label class="fieldset w-full">
                                <div class="label">
                                    <span>{{ __('To Document Number') }}</span>
                                </div>
                                <x-input name="end_document_number" type="number" value="{{ old('end_document_number') }}" />
                            </label>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <x-date-picker name="start_date" id="start_date" title="{{ __('From Date') }}" :value="old('start_date')" />
                        </div>

                        <div>
                            <x-date-picker name="end_date" id="end_date" title="{{ __('To Date') }}" :value="old('end_date')" />
                        </div>
                    </div>

                    <div>
                        <label class="fieldset w-full">
                            <div class="label">
                                <span>{{ __('By title or description') }}</span>
                            </div>
                            <x-input name="text" value="{{ old('text') }}" />
                        </label>
                    </div>
                </div>

                @php
                    $allColumns = \App\Services\DocumentImportExportService::ALL_COLUMNS;
                    $requiredCols = \App\Services\DocumentImportExportService::IMPORT_REQUIRED_COLUMNS;
                    $selectedCols = old('columns', $allColumns);
                    $initialState = array_combine(
                        $allColumns,
                        array_map(fn ($c) => in_array($c, $selectedCols), $allColumns)
                    );
                @endphp

                <div class="mt-2" x-data="{
                         cols: @js($initialState),
                         required: @js($requiredCols),
                         get hasWarning() {
                             return this.required.some(col => !this.cols[col]);
                         }
                     }">

                    <p class="text-sm font-medium text-base-content/70 mb-2">{{ __('CSV Columns') }}</p>

                    <div x-show="hasWarning" x-cloak class="alert alert-warning text-sm mb-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                        {{ __('Deselecting required columns will prevent re-importing this file correctly.') }}
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2">
                        @foreach($allColumns as $col)
                            @php $isRequired = in_array($col, $requiredCols); @endphp
                            <x-checkbox name="columns[]" :value="$col" :title="$col" :id="'col-'.$col" x-model="cols['{{ $col }}']" />
                        @endforeach
                    </div>
                </div>

                <div class="card-actions justify-end mt-4">
                    <a href="{{ route('documents.index') }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Download CSV') }}</button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
