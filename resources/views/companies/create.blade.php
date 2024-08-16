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
                        <label class="input input-bordered flex items-center gap-2">
                            {{ __('Company name') }}
                            <input type="text" name="name" class="grow" value="{{ old('name', $company?->name) }}"
                                required />
                        </label>
                    </div>
                    <div class="col-span-2 md:col-span-1 flex gap-x-4">
                        <label for="logo">
                            {{ __('Company logo') }}
                        </label>
                        <input type="file" id="logo" name="logo" class="file-input w-full max-w-xs" accept="image/*" />
                    </div>
                    <div class="col-span-2">

                        <div class="col-span-2">
                            <label id="co_address" class="form-control">
                                <span class="label">
                                    <span class="label-text">{{ __('Address') }}</span>
                                </span>
                                <textarea id="address" name="address" class="textarea textarea-bordered h-24">
                                    {{ old('address', $company?->address) }}
                                </textarea>
                            </label>
                        </div>

                    </div>
                    <div class="col-span-2 md:col-span-1">
                        <label class="input input-bordered flex items-center gap-2">
                            {{ __('Economical Code') }}
                            <input type="text" id="economical_code" name="economical_code" class="grow"
                                value="{{ old('economical_code', $company?->economical_code) }}" />
                        </label>
                    </div>
                    <div class="col-span-2 md:col-span-1">
                        <label class="input input-bordered flex items-center gap-2">
                            {{ __('National Code') }}
                            <input type="text" id="national_code" name="national_code" class="grow"
                                value="{{ old('national_code', $company?->national_code) }}" />
                        </label>
                    </div>
                    <div class="col-span-2 md:col-span-1">
                        <label class="input input-bordered flex items-center gap-2">
                            {{ __('Postal Code') }}
                            <input type="text" id="postal_code" name="postal_code" class="grow"
                                value="{{ old('postal_code', $company?->postal_code) }}" />
                        </label>
                    </div>
                    <div class="col-span-2 md:col-span-1">
                        <label class="input input-bordered flex items-center gap-2">
                            {{ __('Phone number') }}
                            <input type="text" id="phone_number" name="phone_number" class="grow"
                                value="{{ old('phone_number', $company?->phone_number) }}" />
                        </label>
                    </div>
                </fieldset>
                <div class="card-actions">
                    <button type="submit" class="btn btn-pr">{{ __('Create') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>