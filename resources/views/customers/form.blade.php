<div role="tablist" class="tabs tabs-lifted">

    <input type="radio" name="my_tabs_2" role="tab" class="tab" aria-label="اطلاعات اقتصادی" />
    <div role="tabpanel" class="tab-content bg-base-100 border-base-300 rounded-box p-6">

        <fieldset class="grid grid-cols-2 gap-6 border p-5 my-3">
            <legend>حساب 1</legend>
            <div class="col-span-2 md:col-span-1">
                <label id="acc_name_1" class="input input-bordered flex items-center gap-2">
                    نام
                    <input type="text" id="acc_name_1" name="acc_name_1"
                           class="grow" value="{{ old('acc_name_1', $customer->acc_name_1 ?? '') }}"
                           placeholder="نام"/>
                </label>
            </div>
            <div class="col-span-2 md:col-span-1">
                <label id="acc_no_1" class="input input-bordered flex items-center gap-2">
                    شماره حساب
                    <input type="number" id="acc_no_1" name="acc_no_1"
                           class="grow" value="{{ old('acc_no_1', $customer->acc_no_1 ?? '') }}"
                           placeholder="شماره حساب"/>
                </label>
            </div>
            <div class="col-span-2 md:col-span-1">
                <label id="acc_bank_1" class="input input-bordered flex items-center gap-2">
                    بانک
                    <input type="text" id="acc_bank_1" name="acc_bank_1"
                           class="grow" value="{{ old('acc_bank_1', $customer->acc_bank_1 ?? '') }}"
                           placeholder="بانک"/>
                </label>
            </div>
        </fieldset>

        <fieldset class="grid grid-cols-2 gap-6 border p-5 my-3">
            <legend>حساب 2</legend>
            <div class="col-span-2 md:col-span-1">
                <label id="acc_name_2" class="input input-bordered flex items-center gap-2">
                    نام
                    <input type="text" id="acc_name_2" name="acc_name_2"
                           class="grow" value="{{ old('acc_name_2', $customer->acc_name_2 ?? '') }}"
                           placeholder="نام"/>
                </label>
            </div>
            <div class="col-span-2 md:col-span-1">
                <label id="acc_no_2" class="input input-bordered flex items-center gap-2">
                    شماره حساب
                    <input type="number" id="acc_no_2" name="acc_no_2"
                           class="grow" value="{{ old('acc_no_2', $customer->acc_no_2 ?? '') }}"
                           placeholder="شماره حساب"/>
                </label>
            </div>
            <div class="col-span-2 md:col-span-1">
                <label id="acc_bank_2" class="input input-bordered flex items-center gap-2">
                    بانک
                    <input type="text" id="acc_bank_2" name="acc_bank_2"
                           class="grow" value="{{ old('acc_bank_2', $customer->acc_bank_2 ?? '') }}"
                           placeholder="بانک"/>
                </label>
            </div>
        </fieldset>

    </div>

    <input type="radio" name="my_tabs_2" role="tab" class="tab" aria-label="اطلاعات شخصی" checked/>
    <div role="tabpanel" class="tab-content bg-base-100 border-base-300 rounded-box p-6">

        <fieldset class="grid grid-cols-2 gap-6 border p-5 my-3">
            <legend>اطلاعات اصلی</legend>
            <div class="col-span-2 md:col-span-1">
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
            </div>

            <div class="col-span-2 md:col-span-1">
                <label id="code" class="input input-bordered flex items-center gap-2">
                    کد طرف حساب
                    <input type="number" id="code" name="code"
                           class="grow" value="{{ old('code', $customer->code ?? '') }}"
                           placeholder="کد طرف حساب" required/>
                </label>
            </div>

            <div class="col-span-2 md:col-span-1">
                <label id="ecnmcs_code" class="input input-bordered flex items-center gap-2">
                    کد اقتصادی
                    <input type="number" id="ecnmcs_code" name="ecnmcs_code"
                           class="grow" value="{{ old('ecnmcs_code', $customer->ecnmcs_code ?? '') }}"
                           placeholder="کد اقتصادی" />
                </label>
            </div>

            <div class="col-span-2 md:col-span-1">
                <label id="name" class="input input-bordered flex items-center gap-2">
                    نام
                    <input type="text" id="name" name="name"
                           class="grow" value="{{ old('name', $customer->name ?? '') }}"
                           placeholder="نام" required/>
                </label>
            </div>

            <div class="col-span-2 md:col-span-1">
                <label id="personal_code" class="input input-bordered flex items-center gap-2">
                    کد ملی
                    <input type="number" id="personal_code" name="personal_code"
                           class="grow" value="{{ old('personal_code', $customer->personal_code ?? '') }}"
                           placeholder="کد ملی" />
                </label>
            </div>
        </fieldset>

        <fieldset class="grid grid-cols-2 gap-6 border p-5">
            <legend>اطلاعات طرف حساب</legend>
            <div class="col-span-2 md:col-span-1">
                <label id="phone" class="input input-bordered flex items-center gap-2">
                    تلفن
                    <input type="tel" id="phone" name="phone"
                           class="grow" value="{{ old('phone', $customer->phone ?? '') }}"
                           placeholder="تلفن" />
                </label>
            </div>
            <div class="col-span-2 md:col-span-1">
                <label id="fax" class="input input-bordered flex items-center gap-2">
                    فاکس
                    <input type="tel" id="fax" name="fax"
                           class="grow" value="{{ old('fax', $customer->fax ?? '') }}"
                           placeholder="فاکس" />
                </label>
            </div>
            <div class="col-span-2 md:col-span-1">
                <label id="cell" class="input input-bordered flex items-center gap-2">
                    cell
                    <input type="tel" id="cell" name="cell"
                           class="grow" value="{{ old('cell', $customer->cell ?? '') }}"
                           placeholder="cell"/>
                </label>
            </div>
            <div class="col-span-2 md:col-span-1">
                <label id="web_page" class="input input-bordered flex items-center gap-2">
                    وب سایت
                    <input type="text" id="web_page" name="web_page"
                           class="grow" value="{{ old('web_page', $customer->web_page ?? '') }}"
                           placeholder="وب سایت" />
                </label>
            </div>
            <div class="col-span-2 md:col-span-1">
                <label id="email" class="input input-bordered flex items-center gap-2">
                    ایمیل
                    <input type="email" id="name" name="email"
                           class="grow" value="{{ old('email', $customer->email ?? '') }}"
                           placeholder="ایمیل" />
                </label>
            </div>
            <div class="flex items-center">
                <input {{ ( isset($customer) && ($customer->rep_via_email == 1)) ? 'checked' : ''}} type="checkbox"
                       id="rep_via_email" name="rep_via_email"
                       class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                <label for="rep_via_email" class="block mb-2 text-sm font-medium text-gray-900 dark:text-gray-300">اطلاع
                    رسانی از طریق ایمیل</label>
            </div>

            <div class="col-span-2">

                <label id="address" class="form-control">
            <span class="label">
                <span class="label-text">نشانی</span>
            </span>
                    <textarea id="address" name="address"
                              class="textarea textarea-bordered h-24" placeholder="نشانی">{{ old('address', $customer->address ?? '') }}</textarea>
                </label>

            </div>


            <div class="col-span-2 md:col-span-1">
                <label id="postal_code" class="input input-bordered flex items-center gap-2">
                    کد پستی
                    <input type="number" id="postal_code" name="postal_code"
                           class="grow" value="{{ old('postal_code', $customer->postal_code ?? '') }}"
                           placeholder="کد پستی" />
                </label>
            </div>

        </fieldset>

        <fieldset class="grid grid-cols-2 gap-6 border p-5 my-3">
            <legend>اطلاعات تماس</legend>
            <div class="col-span-2 md:col-span-1">
                <label id="connector" class="input input-bordered flex items-center gap-2">
                    رابط
                    <input type="text" id="connector" name="connector"
                           class="grow" value="{{ old('connector', $customer->connector ?? '') }}"
                           placeholder="رابطه" />
                </label>
            </div>

            <div class="col-span-2 md:col-span-1">
                <label id="responsible" class="input input-bordered flex items-center gap-2">
                    responsible
                    <input type="text" id="responsible" name="responsible"
                           class="grow" value="{{ old('responsible', $customer->responsible ?? '') }}"
                           placeholder="responsible" />
                </label>
            </div>
        </fieldset>

        <fieldset class="grid grid-cols-2 gap-6 border p-5 my-3">
            <legend>توضیحات</legend>
            <div class="col-span-2">
                <label id="desc" class="form-control">
            <span class="label">
                <span class="label-text">توضیح</span>
            </span>
                    <textarea id="desc" name="desc"
                              class="textarea textarea-bordered h-24" placeholder="توضیح">{{ old('desc', $customer->desc ?? '') }}</textarea>
                </label>
            </div>

        </fieldset>

    </div>

</div>



