<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create Company') }}
        </h2>
    </x-slot>
    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <span class="card-title">{{ $company ? __('Edit Company') : __('Add Company') }}</span>
            <form action="{{ $company ? route('companies.update', $company->id) : route('companies.store') }}"
                method="POST" enctype="multipart/form-data">
                @csrf
                @isset($company)
                    @method('PATCH')
                @endisset

                <fieldset id="companyForm" class="grid grid-cols-2 gap-6 border p-5 my-3">
                    <legend>{{ __('company') }}</legend>
                    <div class="col-span-2 md:col-span-1">
                        <x-input name="name" id="name" title="{{ __('Company name') }}" :value="old('code', $company->name ?? '')"
                            required />
                    </div>
                    <div class="col-span-2 md:col-span-1">
                        <x-input name="fiscal_year" id="fiscal_year" title="{{ __('Fiscal year') }}" :value="old('fiscal_year', $company->fiscal_year ?? '')"
                            required />
                    </div>
                    <div class="col-span-2 md:col-span-1 flex gap-x-4">
                        <label for="logo">
                            {{ __('Company logo') }}
                        </label>
                        <input type="file" id="logo" name="logo" class="file-input w-full max-w-xs"
                            accept="image/*" />
                    </div>
                    @if ($company)
                        <img class="block w-12 h-auto rounded-full" src="{{ asset('storage/' . $company->logo) }}">
                    @endif
                    <div class="col-span-2">
                        <div class="col-span-2">
                            <x-textarea name="address" id="address" title="{{ __('Address') }}" :value="old('address', $company->address ?? '')" />
                        </div>
                    </div>
                    <div class="col-span-2 md:col-span-1">
                        <x-input name="economical_code" id="economical_code" title="{{ __('Economical Code') }}"
                            :value="old('economical_code', $company->economical_code ?? '')" />
                    </div>
                    <div class="col-span-2 md:col-span-1">
                        <x-input name="national_code" id="national_code" title="{{ __('National Code') }}"
                            :value="old('national_code', $company->national_code ?? '')" />
                    </div>
                    <div class="col-span-2 md:col-span-1">
                        <x-input name="postal_code" id="postal_code" title="{{ __('Postal Code') }}"
                            :value="old('postal_code', $company->postal_code ?? '')" />
                    </div>
                    <div class="col-span-2 md:col-span-1">
                        <x-input name="phone_number" id="phone_number" title="{{ __('Phone number') }}"
                            :value="old('phone_number', $company->phone_number ?? '')" />
                    </div>
                </fieldset>
                <div class="card-actions">
                    <button type="submit" class="btn btn-pr">{{ __('Create') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
