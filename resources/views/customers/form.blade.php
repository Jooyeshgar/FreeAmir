<div class="grid grid-cols-2 bg-gray-100  ">
    <div class="bg-gray-100 p-2 min-h-full ">
        <div>
            <h1>مشخصات هویتی</h1>
            <div class="grid grid-cols-2 gap-1 ">
                <div>
                    @php
                        $hint = '<a class="link text-blue-500" href="' . route('customer-groups.create') . '">' . __('Create new group') . '</a>';
                    @endphp
                    <x-select title="{{ __('Account Plan Group') }}" name="group_id" id="group_id"
                        :options="$groups->pluck('name', 'id')" :selected="$customer->group_id ?? null" :hint="$hint" />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-1 ">
                <div>
                    <x-input title="{{ __('Name') }}" name="name" placeholder="{{ __('Name') }}" :value="old('name', $customer->name ?? '')" />
                </div>
                <div>
                    <x-input title="{{ __('Accounting code') }}"
                        title2="{!! isset($customer) && $customer->subject ? '<a href=' . route('subjects.edit', $customer->subject) . '>' . __('Edit') . '</a>' : '' !!}"
                        name="" disabled placeholder="{{ __('Accounting code') }}" :value="isset($customer) && $customer->subject ? $customer->subject->formattedCode() : ''" />
                </div>

            </div>
            <div class="grid grid-cols-2 gap-1 ">
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
    <div class="bg-gray-200 p-2 rounded-xl">
        <div role="tablist" class="tabs tabs-boxed">
            <input type="radio" name="my_tabs_2" role="tab" class="tab" aria-label="اطلاعات تماس" checked />
            <div role="tabpanel" class="tab-content bg-gray-100  rounded-box p-2">
                <div class="grid grid-cols-3 gap-1 ">
                    <div>
                        <x-input title="{{ __('Phone') }}" name="tel" placeholder="{{ __('Phone') }}" :value="old('tel', $customer->tel ?? '')" />
                    </div>
                    <div>
                        <x-input title="{{ __('Mobile') }}" name="cell" placeholder="{{ __('Mobile') }}"
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
                    <div class="col-span-3">
                        <x-textarea title="{{ __('Address') }}" name="address" id="address" :value="old('address', $customer->address ?? '')" placeholder="{{ __('Address') }}" />
                    </div>
                </div>
            </div>
            <input type="radio" name="my_tabs_2" role="tab" class="tab" aria-label="اطلاعات اقتصادی" />
            <div role="tabpanel" class="tab-content bg-gray-100  rounded-box p-2">
                <h1> حساب ۱</h1>
                <div class="grid grid-cols-3 gap-1 ">
                    <div>
                        <x-input title="{{ __('Name') }}" name="fax" placeholder="{{ __('Name') }}"
                            :value="old('acc_name_1', $customer->acc_name_1 ?? '')" />

                    </div>
                    <div>
                        <x-input title="{{ __('Account number') }}" name="acc_no_1"
                            placeholder="{{ __('Account number') }}" :value="old('acc_name_1', $customer->acc_no_1 ?? '')" />
                    </div>
                    <div>
                        <x-input title="{{ __('Bank') }}" name="acc_bank_1" placeholder="{{ __('Bank') }}"
                            :value="old('acc_bank_1', $customer->acc_bank_1 ?? '')" />
                    </div>
                </div>
                <h1> حساب ۲</h1>
                <div class="grid grid-cols-3 gap-1 ">
                    <div>
                        <x-input title="{{ __('Name') }}" name="fax" placeholder="{{ __('Name') }}"
                            :value="old('acc_name_2', $customer->acc_name_2 ?? '')" />
                    </div>
                    <div>
                        <x-input title="{{ __('Account number') }}" name="acc_no_2"
                            placeholder="{{ __('Account number') }}" :value="old('acc_name_2', $customer->acc_no_2 ?? '')" />
                    </div>
                    <div>
                        <x-input title="{{ __('Bank') }}" name="acc_bank_1" placeholder="{{ __('Bank') }}"
                            :value="old('acc_bank_2', $customer->acc_bank_2 ?? '')" />
                    </div>
                </div>
            </div>
            <input type="radio" name="my_tabs_2" role="tab" class="tab" aria-label="سایر اطلاعات" />
            <div role="tabpanel" class="tab-content bg-gray-100  rounded-box p-2">
                <div class="grid grid-cols-2 gap-1 ">
                    <div>
                        <x-input title="{{ __('connector') }}" name="connector" placeholder="{{ __('connector') }}"
                            :value="old('connector', $customer->connector ?? '')" />
                    </div>
                    <div>
                        <x-input title="{{ __('responsible') }}" name="responsible"
                            placeholder="{{ __('responsible') }}" :value="old('responsible', $customer->responsible ?? '')" />
                    </div>
                    <div class="col-span-2">
                        <x-textarea title="{{ __('Description') }}" name="desc" id="desc" :value="old('desc', $customer->desc ?? '')" placeholder="{{ __('Desc') }}" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>