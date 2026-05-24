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

                <div class="card-actions justify-end mt-4">
                    <a href="{{ route('documents.index') }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Download CSV') }}</button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
