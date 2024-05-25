<div class="grid grid-cols-2 bg-gray-100  " >
    <div class="bg-gray-100 p-2 min-h-full ">
        <div>
            <h1>مشخصات هویتی</h1>
            <div>
                <label id="group_id" class="form-control w-full">
                    <select id="group_id" name="group_id" class="select select-bordered rounded-md"  required>
                        <option value="">گروه طرف حساب</option>
                        @foreach ($groups as $group)
                            <option
                                value="{{ $group->id }}" {{ (isset($customer) && $customer->group_id == $group->id) ? 'selected' : '' }}>
                                {{ $group->name }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <a class="link text-blue-500" href="{{ route('customer-groups.create') }}">اضافه کردن طرف
                    حساب</a>
            </div>
            <div class="grid grid-cols-2 gap-1 ">
                <div>
                    <x-form-input title="{{ __('نام') }}" name="name"
                                  place-holder="{{ __('نام') }}" :message="$errors->first('name')"
                                  :value="old('name', $customer->name ?? '')"/>
                </div>
                <div>
                    <x-form-input title="{{ __('   کد طرف حساب') }}" name="code"
                                  place-holder="{{ __('   کد طرف حساب') }}" :message="$errors->first('code')"
                                  :value="old('code', $customer->code ?? '')"/>
                </div>

            </div>
            <div class="grid grid-cols-2 gap-1 ">
                <div>
                    <x-form-input title="{{ __('     کد ملی') }}" name="code"
                                  place-holder="{{ __('     کد ملی') }}" :message="$errors->first('personal_code')"
                                  :value="old('personal_code', $customer->personal_code ?? '')"/>
                </div>
                <div>
                    <x-form-input title="{{ __(' کد اقتصادی') }}" name="ecnmcs_code"
                                  place-holder="{{ __('  کد اقتصادی') }}" :message="$errors->first('ecnmcs_code')"
                                  :value="old('ecnmcs_code', $customer->ecnmcs_code ?? '')"/>
                </div>
                <div>
                    <label class="form-control w-full max-w-xs">
                        <div class="label">
                            <span class="label-text">

</span>
                        </div>
                    </label>
                    <label class="input input-bordered flex items-center gap-2 prefix text-gray-300 " dir="ltr">
                        ABR-
                        <input class="grow input  w-full max-w-xs prefix-input" type="text" name="shenase" value="{{old('shenase', $customer->shenase ?? '')}}"  />
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
            <input type="radio" name="my_tabs_2" role="tab" class="tab" aria-label="اطلاعات تماس" checked/>
            <div role="tabpanel" class="tab-content bg-gray-100  rounded-box p-2">
                <div class="grid grid-cols-3 gap-1 ">
                    <div>
                        <x-form-input title="{{ __('تلفن') }}" name="tel"
                                      place-holder="{{ __('تلفن') }}" :message="$errors->first('tel')"
                                      :value="old('tel', $customer->tel ?? '')"/>

                    </div>
                    <div>
                        <x-form-input title="{{ __('موبایل') }}" name="cell"
                                      place-holder="{{ __('موبایل') }}" :message="$errors->first('cell')"
                                      :value="old('cell', $customer->cell ?? '')"/>
                    </div>
                    <div>
                        <x-form-input title="{{ __('فاکس') }}" name="fax"
                                      place-holder="{{ __('فاکس') }}" :message="$errors->first('fax')"
                                      :value="old('fax', $customer->fax ?? '')"/>
                    </div>
                    <div>
                        <x-form-input title="{{ __('ایمیل') }}" name="email"
                                      place-holder="{{ __('ایمیل') }}" :message="$errors->first('email')"
                                      :value="old('email', $customer->email ?? '')"/>

                    </div>
                    <div>
                        <x-form-input title="{{ __(' وب سایت') }}" name="web_page"
                                      place-holder="{{ __(' وب سایت') }}" :message="$errors->first('web_page')"
                                      :value="old('web_page', $customer->web_page ?? '')"/>

                    </div>
                    <div>
                        <x-form-input title="{{ __('   کد پستی') }}" name="postal_code"
                                      :value="old('postal_code', $customer->postal_code ?? '')"
                                      place-holder="{{ __('  کد پستی') }}" :message="$errors->first('postal_code')"/>
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
                            <textarea id="address" name="address"
                                      class="textarea textarea-bordered h-24"
                                      placeholder="نشانی">{{ old('address', $customer->address ?? '') }}</textarea>
                        </label></div>
                </div>
            </div>
            <input type="radio" name="my_tabs_2" role="tab" class="tab  	" aria-label="اطلاعات اقتصادی"/>
            <div role="tabpanel" class="tab-content bg-gray-100  rounded-box p-2"><h1> حساب ۱</h1>
                <div class="grid grid-cols-3 gap-1 ">
                    <div>
                        <x-form-input title="{{ __(' نام') }}" name="fax"
                                      place-holder="{{ __(' نام') }}" :message="$errors->first('acc_name_1')"
                                      :value="old('acc_name_1', $customer->acc_name_1 ?? '')"/>

                    </div>
                    <div>
                        <x-form-input title="{{ __('  شماره حساب') }}" name="acc_no_1"
                                      place-holder="{{ __('  شماره حساب') }}" :message="$errors->first('acc_no_1')"
                                      :value="old('acc_name_1', $customer->acc_no_1 ?? '')"/>
                    </div>
                    <div>
                        <x-form-input title="{{ __(' بانک') }}" name="acc_bank_1"
                                      place-holder="{{ __('  بانک') }}" :message="$errors->first('acc_bank_1')"
                                      :value="old('acc_bank_1', $customer->acc_bank_1 ?? '')"/>
                    </div>
                </div>
                <h1> حساب ۲</h1>
                <div class="grid grid-cols-3 gap-1 ">
                    <div>
                        <x-form-input title="{{ __(' نام') }}" name="fax"
                                      place-holder="{{ __(' نام') }}" :message="$errors->first('acc_name_2')"
                                      :value="old('acc_name_2', $customer->acc_name_2 ?? '')"/>
                    </div>
                    <div>
                        <x-form-input title="{{ __('  شماره حساب') }}" name="acc_no_2"
                                      place-holder="{{ __('  شماره حساب') }}" :message="$errors->first('acc_no_1')"
                                      :value="old('acc_name_2', $customer->acc_no_2 ?? '')"/>
                    </div>
                    <div>
                        <x-form-input title="{{ __(' بانک') }}" name="acc_bank_1"
                                      place-holder="{{ __('  بانک') }}" :message="$errors->first('acc_bank_2')"
                                      :value="old('acc_bank_2', $customer->acc_bank_2?? '')"/>
                    </div>
                </div>
            </div>
            <input type="radio" name="my_tabs_2" role="tab" class="tab" aria-label="سایر اطلاعات"/>
            <div role="tabpanel" class="tab-content bg-gray-100  rounded-box p-2">
                <div class="grid grid-cols-2 gap-1 ">
                    <div>
                        <x-form-input title="{{ __(' رابطه') }}" name="connector"
                                      place-holder="{{ __('  رابطه') }}" :message="$errors->first('connector')"
                                      :value="old('connector', $customer->connector?? '')"/>
                    </div>
                    <div>
                        <x-form-input title="{{ __(' responsible') }}" name="responsible"
                                      place-holder="{{ __('  responsible') }}" :message="$errors->first('responsible')"
                                      :value="old('responsible', $customer->responsible?? '')"/>
                    </div>
                    <div class="col-span-2">
                        <label id="desc" class="form-control">
            <span class="label">
                <span class="label-text">توضیح</span>
            </span>
                            <textarea id="desc" name="desc"
                                      class="textarea textarea-bordered h-24"
                                      placeholder="توضیح">{{ old('desc', $customer->desc ?? '') }}</textarea>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>



