<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="p-4">
        <div>
            <div class="text-sm font-semibold text-gray-600 mb-3">{{ __('Identity Information') }}</div>
            <div class="grid grid-cols-2 gap-1 ">
                <div>
                    @php
                        $hint = '<a class="link text-blue-500" href="' . route('customer-groups.create') . '">' . __('Create new group') . '</a>';
                    @endphp
                    <x-select title="{{ __('Account Plan Group') }}" name="group_id" id="group_id"
                        :options="$groups->pluck('name', 'id')" :selected="old('group_id', $customer->group_id ?? null)" :hint="$hint" />
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <x-input title="{{ __('Name') }}" name="name" placeholder="{{ __('Name') }}" :value="old('name', $customer->name ?? '')" />
                </div>
                <div>
                    <x-input title="{{ __('Accounting code') }}"
                        title2="{!! isset($customer) && $customer->subject ? '<a href=' . route('subjects.edit', $customer->subject) . '>' . __('Edit') . '</a>' : '' !!}"
                        name="accounting_code" disabled placeholder="{{ __('Accounting code') }}" :value="isset($customer) && $customer->subject ? $customer->subject->formattedCode() : ''" />
                </div>

            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <x-input title="{{ __('National ID') }}" name="personal_code" placeholder="{{ __('National ID') }}"
                        :value="old('personal_code', $customer->personal_code ?? '')" />
                </div>
                <div>
                    <x-input title="{{ __('Economic code') }}" name="ecnmcs_code"
                        placeholder="{{ __('Economic code') }}" :value="old('ecnmcs_code', $customer->ecnmcs_code ?? '')" />
                </div>
            </div>
        </div>

    </div>
    <div class="p-4" x-data="{ activeTab: 'contact' }">
        <div class="flex flex-wrap gap-2 mb-4 bg-base-200 rounded-box p-2">
            <button type="button"
                class="btn btn-sm rounded-full"
                :class="activeTab === 'contact' ? 'btn-primary' : 'btn-ghost'"
                @click="activeTab = 'contact'"
                aria-label="{{ __('Contact Information') }}">
                {{ __('Contact Information') }}
            </button>
            <button type="button"
                class="btn btn-sm rounded-full"
                :class="activeTab === 'financial' ? 'btn-primary' : 'btn-ghost'"
                @click="activeTab = 'financial'"
                aria-label="{{ __('Financial Information') }}">
                {{ __('Financial Information') }}
            </button>
            <button type="button"
                class="btn btn-sm rounded-full"
                :class="activeTab === 'other' ? 'btn-primary' : 'btn-ghost'"
                @click="activeTab = 'other'"
                aria-label="{{ __('Other Information') }}">
                {{ __('Other Information') }}
            </button>
        </div>
        <div x-show="activeTab === 'contact'" x-cloak>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <x-input title="{{ __('Mobile') }}" name="phone" placeholder="{{ __('Mobile') }}" :value="old('phone', $customer->phone ?? '')" />
                </div>
                <div>
                    <x-input title="{{ __('Cell') }}" name="cell" placeholder="{{ __('Cell') }}"
                        :value="old('cell', $customer->cell ?? '')" />
                </div>
                <div>
                    <x-input title="{{ __('Fax') }}" name="fax" placeholder="{{ __('Fax') }}" :value="old('fax', $customer->fax ?? '')" />
                </div>
                <div>
                    <x-input title="{{ __('Email') }}" name="email" placeholder="{{ __('Email') }}"
                        :value="old('email', $customer->email ?? '')" />

                </div>
                <div>
                    <x-input title="{{ __('Website') }}" name="web_page" placeholder="{{ __('Website') }}"
                        :value="old('web_page', $customer->web_page ?? '')" />
                </div>
                <div>
                    <x-input title="{{ __('Postal code') }}" name="postal_code" :value="old('postal_code', $customer->postal_code ?? '')" placeholder="{{ __('Postal code') }}" />
                </div>
                <div class="md:col-span-3">
                    <x-textarea title="{{ __('Address') }}" name="address" id="address" :value="old('address', $customer->address ?? '')" placeholder="{{ __('Address') }}" />
                </div>
            </div>
        </div>
        <div x-show="activeTab === 'financial'" x-cloak>
            <div class="text-sm font-semibold text-gray-600 mb-3">{{ __('Account 1') }}</div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 border-b pb-4">
                <div>
                    <x-input title="{{ __('Name') }}" name="acc_name_1" placeholder="{{ __('Name') }}"
                        :value="old('acc_name_1', $customer->acc_name_1 ?? '')" />
                </div>
                <div>
                    <x-input title="{{ __('Account number') }}" name="acc_no_1"
                        placeholder="{{ __('Account number') }}" :value="old('acc_no_1', $customer->acc_no_1 ?? '')" />
                </div>
                <div>
                    <x-input title="{{ __('Bank') }}" name="acc_bank_1" placeholder="{{ __('Bank') }}"
                        :value="old('acc_bank_1', $customer->acc_bank_1 ?? '')" />
                </div>
            </div>
            <div class="text-sm font-semibold text-gray-600 mb-3 mt-4">{{ __('Account 2') }}</div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <x-input title="{{ __('Name') }}" name="acc_name_2" placeholder="{{ __('Name') }}"
                        :value="old('acc_name_2', $customer->acc_name_2 ?? '')" />
                </div>
                <div>
                    <x-input title="{{ __('Account number') }}" name="acc_no_2"
                        placeholder="{{ __('Account number') }}" :value="old('acc_no_2', $customer->acc_no_2 ?? '')" />
                </div>
                <div>
                    <x-input title="{{ __('Bank') }}" name="acc_bank_2" placeholder="{{ __('Bank') }}"
                        :value="old('acc_bank_2', $customer->acc_bank_2 ?? '')" />
                </div>
            </div>
        </div>
        <div x-show="activeTab === 'other'" x-cloak>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <x-input title="{{ __('connector') }}" name="connector" placeholder="{{ __('connector') }}"
                        :value="old('connector', $customer->connector ?? '')" />
                </div>
                <div>
                    <x-input title="{{ __('responsible') }}" name="responsible"
                        placeholder="{{ __('responsible') }}" :value="old('responsible', $customer->responsible ?? '')" />
                </div>
                <div class="md:col-span-2">
                    <x-textarea title="{{ __('Description') }}" name="desc" id="desc" :value="old('desc', $customer->desc ?? '')" placeholder="{{ __('Desc') }}" />
                </div>
            </div>
        </div>
    </div>
</div>