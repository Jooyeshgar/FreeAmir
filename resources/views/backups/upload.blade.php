<x-app-layout :title="__('Upload Backup')">
    <x-show-message-bags />

    <div class="card bg-base-100 ">
        <div class="card-body">
            <span class="card-title">{{ __('Upload Backup') }}</span>
            <form action="{{ route('backups.import') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2 md:col-span-1">
                        <x-input title="{{ __('Company name') }}" name="company_name" id="company_name"
                            :value="old('company_name', '')" placeholder="{{ __('Please enter the company name') }}" />
                    </div>
                    <div class="col-span-2 md:col-span-1" x-data="{ fiscalYear: '{{ old('fiscal_year', '') }}' }">
                        <x-input name="fiscal_year" id="fiscal_year" title="{{ __('Fiscal year') }}"
                            placeholder="{{ convertToFarsi('1405') }}"
                            x-on:input="fiscalYear = $store.utils.cleanupNumber($event.target.value)"
                            x-effect="$el.value = $store.utils.convertToFarsi($store.utils.cleanupNumber(fiscalYear) || '')" />
                        <x-input name="fiscal_year" x-bind:value="fiscalYear" hidden />
                    </div>
                    <div class="col-span-2 md:col-span-1 w-64 max-w-md">
                        <x-file-input name="file" title="{{ __('File') }} ({{ __('zip') }})" accept=".zip" />
                    </div>
                </div>
                <div class="card-actions justify-end">
                    <button type="submit" class="btn btn-primary"> {{ __('Upload') }} </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
