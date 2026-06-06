<x-app-layout :title="__('Edit Company')">
    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <span class="card-title">{{ __('Edit Company') }}</span>
            <form action="{{ route('companies.update', $company->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PATCH')

                <fieldset id="companyForm" class="grid grid-cols-2 gap-6 border p-5 my-3">
                    <legend>{{ __('company') }}</legend>
                    <div class="col-span-2 md:col-span-1">
                        <x-input name="name" id="name" title="{{ __('Company name') }}" :value="old('name', $company->name ?? '')" required />
                    </div>
                    <div class="col-span-2 md:col-span-1">
                        <x-input name="fiscal_year" id="fiscal_year" title="{{ __('Fiscal year') }}" :value="old('fiscal_year', $company->fiscal_year ?? '')" required />
                    </div>
                    <div class="col-span-2 md:col-span-1">
                        <x-input name="currency" id="currency" title="{{ __('Currency') }}" :value="old('currency', $company->currency ?? '')" />
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <div class="col-span-2 md:col-span-1">
                            <label class="label" for="logo">{{ __('Company logo') }}</label>
                            <input type="file" id="logo" name="logo" class="file-input w-full max-w-xs" accept="image/*" />
                        </div>
                    </div>
                    <img class="block w-12 h-auto rounded-full" src="{{ asset("storage/{$company->logo}") }}">
                    <div class="col-span-2 md:col-span-1">
                        <x-input name="moadian_username" id="moadian_username" title="{{ __('Moadian Username') }}" :value="old('moadian_username', $company->moadian_username ?? '')" />
                    </div>
                    <div class="col-span-2 md:col-span-1">
                        <x-input name="tax_id" id="tax_id" title="{{ __('Tax ID') }}" :value="old('tax_id', $company->tax_id ?? '')" />
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <div class="col-span-2 md:col-span-1">
                            <label class="label" for="certificate">{{ __('SSL Certificate') }}</label>
                            <input type="file" id="certificate" name="certificate" class="file-input w-full max-w-xs" accept=".crt" />
                            @if ($company->certificate_path)
                                <p class="text-sm text-base-content/60 mt-1">{{ __('Current file') }}: {{ basename($company->certificate_path) }}</p>
                            @endif
                        </div>
                        <div class="col-span-2 md:col-span-1">
                            <label class="label" for="private_key">{{ __('Private Key') }}</label>
                            <input type="file" id="private_key" name="private_key" class="file-input w-full max-w-xs" accept=".pem" />
                            @if ($company->private_key_path)
                                <p class="text-sm text-base-content/60 mt-1">{{ __('Current file') }}: {{ basename($company->private_key_path) }}</p>
                            @endif
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
                    <button type="submit" class="btn btn-pr">{{ __('Edit') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
