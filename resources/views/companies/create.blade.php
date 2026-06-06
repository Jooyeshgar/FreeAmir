@php
    use App\Enums\FiscalYearSection;
@endphp

<x-app-layout :title="__('Create Company')">
    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <span class="card-title">{{ __('Add Company') }}</span>
            <form action="{{ route('companies.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <fieldset id="previousYears" class="grid grid-cols-2 gap-6 border p-5 my-3">
                    <legend>{{ __('Previous Years') }}</legend>
                    <div class="fieldset">
                        <label for="source_year_id" class="label">
                            <span>{{ __('Copy Data From') }}</span>
                        </label>
                        <select class="select  w-full" id="source_year_id" name="source_year_id" required>
                            <option value="">{{ __('Select Source Fiscal Year') }}</option>
                            @foreach ($previousYears as $year)
                                <option value="{{ $year->id }}" {{ old('source_year_id') == $year->id ? 'selected' : '' }}>{{ $year->name }} - {{ $year->fiscal_year }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="fieldset">
                        <label class="label">
                            <span>{{ __('Select Tables to Copy') }}</span>
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
                                    @php $oldTables = old('tables_to_copy', array_map(fn ($c) => $c->value, FiscalYearSection::cases())); @endphp
                                    @foreach (FiscalYearSection::ui() as $key => $value)
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="tables_to_copy[]" value="{{ $key }}" id="table_{{ $key }}"
                                                    class="checkbox" {{ in_array($key, $oldTables) ? 'checked' : '' }}>
                                            </td>
                                            <td>
                                                <label for="table_{{ $key }}">{{ $value }}</label>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </fieldset>

                <fieldset id="companyForm" class="grid grid-cols-2 gap-6 border p-5 my-3">
                    <legend>{{ __('company') }}</legend>
                    <div class="col-span-2 md:col-span-1">
                        <x-input name="name" id="name" title="{{ __('Company name') }}" :value="old('name', $company->name ?? '')" required />
                    </div>
                    <div class="col-span-2 md:col-span-1">
                        <x-input name="fiscal_year" id="fiscal_year" title="{{ __('Fiscal year') }}" :value="old('fiscal_year', $company->fiscal_year ?? '')" required />
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <div class="col-span-2 md:col-span-1">
                            <label class="label" for="logo">{{ __('Company logo') }}</label>
                            <input type="file" id="logo" name="logo" class="file-input w-full max-w-xs" accept="image/*" />
                        </div>
                    </div>
                    <div class="col-span-2 md:col-span-1">
                        <x-input name="currency" id="currency" title="{{ __('Currency') }}" :value="old('currency', $company->currency ?? '')" />
                    </div>
                    <div class="col-span-2 md:col-span-1">
                        <x-input name="moadian_username" id="moadian_username" title="{{ __('Moadian Username') }}" :value="old('moadian_username', '')" />
                    </div>
                    <div class="col-span-2 md:col-span-1">
                        <x-input name="tax_id" id="tax_id" title="{{ __('Tax ID') }}" :value="old('tax_id', '')" />
                    </div>
                    <div class="flex gap-2">
                        <div class="col-span-2 md:col-span-1">
                            <label class="label" for="certificate">{{ __('SSL Certificate') }}</label>
                            <input type="file" id="certificate" name="certificate" class="file-input w-full max-w-xs" accept=".crt" />
                            @error('certificate')
                                <p class="text-sm text-error mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="col-span-2 md:col-span-1">
                            <label class="label" for="private_key">{{ __('Private Key') }}</label>
                            <input type="file" id="private_key" name="private_key" class="file-input w-full max-w-xs" accept=".pem" />
                            @error('private_key')
                                <p class="text-sm text-error mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    <div class="col-span-2">
                        <div class="col-span-2">
                            <x-textarea name="address" id="address" title="{{ __('Address') }}" :value="old('address', $company->address ?? '')" />
                        </div>
                    </div>
                    <div class="col-span-2 md:col-span-1">
                        <x-input name="economical_code" id="economical_code" title="{{ __('Economical Code') }}" :value="old('economical_code', $company->economical_code ?? '')" />
                    </div>
                    <div class="col-span-2 md:col-span-1">
                        <x-input name="national_code" id="national_code" title="{{ __('National Code') }}" :value="old('national_code', $company->national_code ?? '')" />
                    </div>
                    <div class="col-span-2 md:col-span-1">
                        <x-input name="postal_code" id="postal_code" title="{{ __('Postal Code') }}" :value="old('postal_code', $company->postal_code ?? '')" />
                    </div>
                    <div class="col-span-2 md:col-span-1">
                        <x-input name="phone_number" id="phone_number" title="{{ __('Phone number') }}" :value="old('phone_number', $company->phone_number ?? '')" />
                    </div>
                </fieldset>
                <div class="card-actions">
                    <button type="submit" class="btn btn-pr">{{ __('Create') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
