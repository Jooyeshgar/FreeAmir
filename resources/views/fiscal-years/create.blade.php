@php use App\Enums\FiscalYearSection; @endphp

<x-platform-layout :title="__('Create Fiscal Year')">
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <h1 class="card-title">{{ __('Create Fiscal Year') }}</h1>
            <form action="{{ route('fiscal-years.store') }}" method="POST">
                @csrf
                <fieldset class="my-3 grid grid-cols-1 gap-6 border p-5 sm:grid-cols-2">
                    <legend>{{ __('Fiscal year') }}</legend>
                    <div>
                        <label for="company_id" class="label">{{ __('Company') }}</label>
                        <select id="company_id" name="company_id" required class="select select-bordered w-full">
                            <option value="">{{ __('Select Company') }}</option>
                            @foreach ($companies as $company)
                                <option value="{{ $company->id }}" @selected(old('company_id', request('company_id')) == $company->id)>{{ $company->name }}</option>
                            @endforeach
                        </select>
                        @error('company_id') <p class="text-error text-sm">{{ $message }}</p> @enderror
                    </div>
                    <x-input name="year" id="year" title="{{ __('Fiscal year') }}" :value="old('year')" required />
                    <div class="sm:col-span-2">
                        <label for="source_year_id" class="label">{{ __('Copy Data From') }}</label>
                        <select id="source_year_id" name="source_year_id" class="select select-bordered w-full">
                            <option value="">{{ __('Select Source Fiscal Year') }}</option>
                            @foreach ($sources as $source)
                                <option value="{{ $source->id }}" data-company-id="{{ $source->company_id }}" @selected(old('source_year_id') == $source->id)>{{ $source->company->name }} - {{ localizeNumber($source->year) }}</option>
                            @endforeach
                        </select>
                        @error('source_year_id') <p class="text-error text-sm">{{ $message }}</p> @enderror
                    </div>
                    <fieldset class="sm:col-span-2 border p-4">
                        <legend>{{ __('Select Tables to Copy') }}</legend>
                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach (FiscalYearSection::ui() as $key => $label)
                                <label class="flex items-center gap-2"><input type="checkbox" name="tables_to_copy[]" value="{{ $key }}" class="checkbox checkbox-sm" @checked(in_array($key, old('tables_to_copy', array_column(FiscalYearSection::cases(), 'value'))))>{{ $label }}</label>
                            @endforeach
                        </div>
                        @error('tables_to_copy.*') <p class="text-error text-sm">{{ $message }}</p> @enderror
                    </fieldset>
                </fieldset>
                <div class="card-actions">
                    <button type="submit" class="btn btn-primary">{{ __('Create') }}</button>
                    <a href="{{ route('fiscal-years.index') }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
</x-platform-layout>
