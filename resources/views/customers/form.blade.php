<div class="grid grid-cols-2 bg-gray-100 ">
    <div class="bg-gray-100 p-4">


        <div>
            <h1>مشخصات هویتی</h1>

            <div>
                <label id="group_id" class="form-control w-full">
                    <select id="group_id" name="group_id" class="select select-bordered" required>
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
                    <label id="name" class="label-text">
                        نام
                    </label>
                    <input type="text" id="name" name="name"
                           class="w-full p-2 border" value="{{ old('name', $customer->name ?? '') }}"
                           placeholder="نام" required/>

                </div>
                <div>
                    <label id="code" class="label-text">
                        کد طرف حساب
                    </label>
                        <input type="number" id="code" name="code"
                               class="w-full p-2 border" value="{{ old('code', $customer->code ?? '') }}"
                               placeholder="کد طرف حساب" required/>

                </div>



            </div>
            <div class="grid grid-cols-2 gap-1 ">
                <div>
                    <label id="personal_code" class="label-text">
                        کد ملی
                    </label>
                    <input type="number" id="personal_code" name="personal_code"
                           class="w-full p-2 border" value="{{ old('personal_code', $customer->personal_code ?? '') }}"
                           placeholder="کد ملی"/>

                </div>
                <div>
                    <label id="ecnmcs_code" class="label-text">
                        کد اقتصادی
                        <input type="number" id="ecnmcs_code" name="ecnmcs_code"
                               class="w-full p-2 border" value="{{ old('ecnmcs_code', $customer->ecnmcs_code ?? '') }}"
                               placeholder="کد اقتصادی"/>
                    </label>
                </div>
            </div>


        </div>

    </div>
    <div class="bg-gray-200 p-4 rounded-xl">
        <div role="tablist" class="tabs tabs-boxed	">
            <input type="radio" name="my_tabs_2" role="tab" class="tab" aria-label="اطلاعات تماس" checked/>
            <div role="tabpanel" class="tab-content bg-gray-100  rounded-box p-4">

                <div class="grid grid-cols-3 gap-1 ">
                    <div>
                        <label class="label-text" for="tel">تلفن</label>

                        <input class="w-full p-2 border" type="tel" id="phone" name="phone"
                               value="{{ old('phone', $customer->phone ?? '') }}"
                               placeholder="تلفن"/>

                    </div>
                    <div>
                        <label class="label-text" for="tel">موبایل</label>

                        <input type="tel" id="cell" name="cell" class="w-full p-2 border"
                               value="{{ old('cell', $customer->cell ?? '') }}"
                               placeholder="cell"/>

                    </div>
                    <div>
                        <label for="tel" class="label-text">فاکس</label>
                        <input type="tel" id="fax" name="fax" class="w-full p-2 border"
                               value="{{ old('fax', $customer->fax ?? '') }}"
                               placeholder="فاکس"/>

                    </div>


                    <div>
                        <label id="email" class="label-text">
                            ایمیل
                        </label>
                        <input type="email" id="name" name="email"
                               class="w-full p-2 border" value="{{ old('email', $customer->email ?? '') }}"
                               placeholder="ایمیل"/>

                    </div>
                    <div>
                        <label id="email" class="label-text">
                            وب سایت
                        </label>
                        <input type="text" id="web_page" name="web_page"
                               class="w-full p-2 border" value="{{ old('web_page', $customer->web_page ?? '') }}"
                               placeholder="وب سایت"/>

                    </div>
                    <div>
                        <label id="postal_code" class="label-text">
                            کد پستی
                        </label>
                        <input type="number" id="postal_code" name="postal_code"
                               class="w-full p-2 border" value="{{ old('postal_code', $customer->postal_code ?? '') }}"
                               placeholder="کد پستی"/>

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
                    <div class="col-span-3">

                        <label id="address" class="form-control">
            <span class="label">
                <span class="label-text">نشانی</span>
            </span>
                            <textarea id="address" name="address"
                                      class="textarea textarea-bordered h-24"
                                      placeholder="نشانی">{{ old('address', $customer->address ?? '') }}</textarea>
                        </label>

                    </div>


                </div>


            </div>
            <input type="radio" name="my_tabs_2" role="tab" class="tab  	" aria-label="اطلاعات اقتصادی"/>
            <div role="tabpanel" class="tab-content bg-base-100  rounded-box p-6">

                <h1> حساب ۱</h1>
                <div class="grid grid-cols-3 gap-1 ">
                    <div>
                        <label id="acc_name_1" class="label-text">
                            نام
                        </label>
                        <input type="text" id="acc_name_1" name="acc_name_1"
                               class="w-full p-2 border" value="{{ old('acc_name_1', $customer->acc_name_1 ?? '') }}"
                               placeholder="نام"/>

                    </div>
                    <div>
                        <label id="acc_no_1" class="label-text">
                            شماره حساب
                            <input type="number" id="acc_no_1" name="acc_no_1"
                                   class="w-full p-2 border" value="{{ old('acc_no_1', $customer->acc_no_1 ?? '') }}"
                                   placeholder="شماره حساب"/>
                        </label>
                    </div>
                    <div>
                        <label id="acc_bank_1" class="label-text">
                            بانک
                            <input type="text" id="acc_bank_1" name="acc_bank_1"
                                   class="w-full p-2 border"
                                   value="{{ old('acc_bank_1', $customer->acc_bank_1 ?? '') }}"
                                   placeholder="بانک"/>
                        </label>
                    </div>


                </div>
                <h1> حساب ۲</h1>
                <div class="grid grid-cols-3 gap-1 ">
                    <div>
                        <label id="acc_name_2" class="label-text">
                            نام
                            <input type="text" id="acc_name_2" name="acc_name_2"
                                   class="w-full p-2 border"
                                   value="{{ old('acc_name_2', $customer->acc_name_2 ?? '') }}"
                                   placeholder="نام"/>
                        </label>
                    </div>
                    <div>
                        <label id="acc_no_2" class="label-text">
                            شماره حساب
                            <input type="number" id="acc_no_2" name="acc_no_2"
                                   class="w-full p-2 border" value="{{ old('acc_no_2', $customer->acc_no_2 ?? '') }}"
                                   placeholder="شماره حساب"/>
                        </label>
                    </div>
                    <div>
                        <label id="acc_bank_2" class="label-text">
                            بانک
                            <input type="text" id="acc_bank_2" name="acc_bank_2"
                                   class="w-full p-2 border"
                                   value="{{ old('acc_bank_2', $customer->acc_bank_2 ?? '') }}"
                                   placeholder="بانک"/>
                        </label>
                    </div>

                </div>
            </div>

            <input type="radio" name="my_tabs_2" role="tab" class="tab" aria-label="سایر اطلاعات"/>
            <div role="tabpanel" class="tab-content bg-base-100  rounded-box p-6">


                <div class="grid grid-cols-2 gap-1 ">

                    <div>
                        <label id="connector" class="label-text">
                            رابط
                        </label>
                        <input type="text" id="connector" name="connector"
                               class="w-full p-2 border" value="{{ old('connector', $customer->connector ?? '') }}"
                               placeholder="رابطه"/>

                    </div>

                    <div>
                        <label id="responsible" class="label-text">

                            responsible
                        </label>
                        <input type="text" id="responsible" name="responsible"
                               class="w-full p-2 border" value="{{ old('responsible', $customer->responsible ?? '') }}"
                               placeholder="responsible"/>
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



