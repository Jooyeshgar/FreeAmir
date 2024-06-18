<div class="grid grid-cols-2 bg-gray-100  ">
    <div class="bg-gray-100 p-2 min-h-full ">
        <div>
            <h1>مشخصات هویتی</h1>
            <div class="grid grid-cols-2 gap-1 ">
                <div>
                    <label id="group_id" class="form-control w-full">
                        <select id="group_id" name="group_id" class="select select-bordered rounded-md" required>
                            <option value="">گروه طرف حساب</option>
                            @foreach ($groups as $group)
                                <option value="{{ $group->id }}" {{ isset($customer) && $customer->group_id == $group->id ? 'selected' : '' }}>
                                    {{ $group->name }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <div>
                    <label id="group_id" class="form-control w-full">
                        <select id="group_id" name="group_id" class="select select-bordered rounded-md mx-0.5" required>
                            <option value="">دسته بندی</option>

                        </select>
                    </label>
                </div>
                <a class="link text-blue-500" href="{{ route('customer-groups.create') }}">اضافه کردن طرف
                    حساب</a>
            </div>
            <div class="grid grid-cols-2 gap-1 ">
                <div>
                    <x-form-input title="{{ __('Name') }}" name="name" place-holder="{{ __('Name') }}" :value="old('name', $customer->name ?? '')" />
                </div>
                <div>
                    <x-form-input title="{{ __('Accountting code') }}" name="code" place-holder="{{ __('Accountting code') }}" :value="old('code', $customer->code ?? '')" />
                </div>

            </div>
            <div class="grid grid-cols-2 gap-1 ">
                <div>
                    <x-form-input title="{{ __('National ID') }}" name="code" place-holder="{{ __('National ID') }}" :value="old('personal_code', $customer->personal_code ?? '')" />
                </div>
                <div>
                    <x-form-input title="{{ __('Economic code') }}" name="ecnmcs_code" place-holder="{{ __('Economic code') }}" :value="old('ecnmcs_code', $customer->ecnmcs_code ?? '')" />
                </div>
                <div>
                    <label class="form-control w-full max-w-xs">
                        <div class="label">
                            <span class="label-text">شناسه

                            </span>
                        </div>
                    </label>
                    <label class="input input-bordered flex items-center gap-2 prefix text-gray-300 rounded-md" dir="ltr">
                        ABR-
                        <input class="grow input  w-full max-w-xs prefix-input" type="text" name="shenase" value="{{ old('shenase', $customer->shenase ?? '') }}" />
                        @if ($errors->first('shenase'))
                            <div class="label">
                                <span class="label-text-alt text-red-700">{{ $errors->first('shenase') }}</span>
                            </div>
                        @endif
                    </label>
                </div>
            </div>
        </div>

    </div>
    <div class="bg-gray-200 p-2 rounded-xl">
        <div role="tablist" class="tabs tabs-boxed	">
            <input type="radio" name="my_tabs_2" role="tab" class="tab" aria-label="اطلاعات تماس" checked />
            <div role="tabpanel" class="tab-content bg-gray-100  rounded-box p-2">
                <div class="grid grid-cols-3 gap-1 ">
                    <div>
                        <x-form-input title="{{ __('Phone') }}" name="tel" place-holder="{{ __('Phone') }}" :value="old('tel', $customer->tel ?? '')" />

                    </div>
                    <div>
                        <x-form-input title="{{ __('Mobile') }}" name="cell" place-holder="{{ __('Mobile') }}" :value="old('cell', $customer->cell ?? '')" />
                    </div>
                    <div>
                        <x-form-input title="{{ __('Fax') }}" name="fax" place-holder="{{ __('Fax') }}" :value="old('fax', $customer->fax ?? '')" />
                    </div>
                    <div>
                        <x-form-input title="{{ __('Email') }}" name="email" place-holder="{{ __('Email') }}" :value="old('email', $customer->email ?? '')" />

                    </div>
                    <div>
                        <x-form-input title="{{ __('Website') }}" name="web_page" place-holder="{{ __('Website') }}" :value="old('web_page', $customer->web_page ?? '')" />
                    </div>
                    <div>
                        <x-form-input title="{{ __('Postal code') }}" name="postal_code" :value="old('postal_code', $customer->postal_code ?? '')" place-holder="{{ __('Postal code') }}" />
                    </div>
                    {{--
                    <div class="flex items-center">
                        <input
                            {{ ( isset($customer) && ($customer->rep_via_email == 1)) ? 'checked' : ''}} type="checkbox"
                            id="rep_via_email" name="rep_via_email"
                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                        <label for="rep_via_email"
                               class="block mb-2 text-sm font-medium text-gray-900 dark:text-gray-300">اطلاع
                            رسانی از طریق ایمیل</label>
                    </div>
--}}
                    <div class="col-span-3"><label id="address" class="form-control">
                            <span class="label">
                                <span class="label-text">نشانی</span>
                            </span>
                            <textarea id="address" name="address" class="textarea textarea-bordered h-24" placeholder="نشانی">{{ old('address', $customer->address ?? '') }}</textarea>
                        </label></div>
                </div>
            </div>
            <input type="radio" name="my_tabs_2" role="tab" class="tab  	" aria-label="اطلاعات اقتصادی" />
            <div role="tabpanel" class="tab-content bg-gray-100  rounded-box p-2">
                <h1> حساب ۱</h1>
                <div class="grid grid-cols-3 gap-1 ">
                    <div>
                        <x-form-input title="{{ __('Name') }}" name="fax" place-holder="{{ __('Name') }}" :value="old('acc_name_1', $customer->acc_name_1 ?? '')" />

                    </div>
                    <div>
                        <x-form-input title="{{ __('Account number') }}" name="acc_no_1" place-holder="{{ __('Account number') }}" :value="old('acc_name_1', $customer->acc_no_1 ?? '')" />
                    </div>
                    <div>
                        <x-form-input title="{{ __('Bank') }}" name="acc_bank_1" place-holder="{{ __('Bank') }}" :value="old('acc_bank_1', $customer->acc_bank_1 ?? '')" />
                    </div>
                </div>
                <h1> حساب ۲</h1>
                <div class="grid grid-cols-3 gap-1 ">
                    <div>
                        <x-form-input title="{{ __('Name') }}" name="fax" place-holder="{{ __('Name') }}" :value="old('acc_name_2', $customer->acc_name_2 ?? '')" />
                    </div>
                    <div>
                        <x-form-input title="{{ __('Account number') }}" name="acc_no_2" place-holder="{{ __('Account number') }}" :message="$errors->first('acc_no_1')"
                            :value="old('acc_name_2', $customer->acc_no_2 ?? '')" />
                    </div>
                    <div>
                        <x-form-input title="{{ __('Bank') }}" name="acc_bank_1" place-holder="{{ __('Bank') }}" :value="old('acc_bank_2', $customer->acc_bank_2 ?? '')" />
                    </div>
                </div>
            </div>
            <input type="radio" name="my_tabs_2" role="tab" class="tab" aria-label="سایر اطلاعات" />
            <div role="tabpanel" class="tab-content bg-gray-100  rounded-box p-2">
                <div class="grid grid-cols-2 gap-1 ">
                    <div>
                        <x-form-input title="{{ __('connector') }}" name="connector" place-holder="{{ __('connector') }}" :value="old('connector', $customer->connector ?? '')" />
                    </div>
                    <div>
                        <x-form-input title="{{ __('responsible') }}" name="responsible" place-holder="{{ __('responsible') }}" :message="$errors->first('responsible')"
                            :value="old('responsible', $customer->responsible ?? '')" />
                    </div>
                    <div class="col-span-2">
                        <label id="desc" class="form-control">
                            <span class="label">
                                <span class="label-text">توضیح</span>
                            </span>
                            <textarea id="desc" name="desc" class="textarea textarea-bordered h-24" placeholder="توضیح">{{ old('desc', $customer->desc ?? '') }}</textarea>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
