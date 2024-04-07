<fieldset class="grid grid-cols-2 gap-6 border p-5 my-3">
    <legend>شرکت</legend>
    <div class="col-span-2 md:col-span-1">
        <label id="co_name" class="input input-bordered flex items-center gap-2">
            نام شرکت شما
            <input type="text" id="co_name" name="co_name"
                   class="grow" value="{{ old('co_name', $configs['co_name'] ?? '') }}"
                   placeholder="نام شرکت شما" required/>
        </label>
    </div>
    <div class="col-span-2 md:col-span-1 flex gap-x-4">
        <label for="co_logo">
            لوگوی شرکت خود را انتخاب کنید
        </label>
            <input type="file" id="co_logo" name="co_logo" class="file-input w-full max-w-xs" accept="image/*"/>


    </div>
    <img class="block w-12 h-auto rounded-full" src="{{ asset("storage/{$configs['co_logo']}") }}" alt="{{ $configs['co_logo'] }}">
    <div class="col-span-2">

        <div class="col-span-2">
            <label id="co_address" class="form-control">
            <span class="label">
                <span class="label-text"> نشانی شرکت شما</span>
            </span>
                <textarea id="co_address" name="co_address"
                          class="textarea textarea-bordered h-24" placeholder="نشانی شرکت شما">{{ old('co_address', $configs['co_address'] ?? '') }}</textarea>
            </label>
        </div>

    </div>
    <div class="col-span-2 md:col-span-1">
        <label id="co_economical_code" class="input input-bordered flex items-center gap-2">
           کد اقتصادی شما
            <input type="text" id="co_economical_code" name="co_economical_code"
                   class="grow" value="{{ old('co_economical_code', $configs['co_economical_code'] ?? '') }}"
                   placeholder="کد اقتصادی شما"/>
        </label>
    </div>
    <div class="col-span-2 md:col-span-1">
        <label id="co_national_code" class="input input-bordered flex items-center gap-2">
            کد ملی شما
            <input type="text" id="co_national_code" name="co_national_code"
                   class="grow" value="{{ old('co_national_code', $configs['co_national_code'] ?? '') }}"
                   placeholder="کد ملی شما"/>
        </label>
    </div>
    <div class="col-span-2 md:col-span-1">
        <label id="co_postal_code" class="input input-bordered flex items-center gap-2">
            کد پستی شما
            <input type="text" id="co_postal_code" name="co_postal_code"
                   class="grow" value="{{ old('co_postal_code', $configs['co_postal_code'] ?? '') }}"
                   placeholder="کد پستی شما"/>
        </label>
    </div>
    <div class="col-span-2 md:col-span-1">
        <label id="co_phone_number" class="input input-bordered flex items-center gap-2">
            شماره تلفن شما
            <input type="text" id="co_phone_number" name="co_phone_number"
                   class="grow" value="{{ old('co_phone_number', $configs['co_phone_number'] ?? '') }}"
                   placeholder="شماره تلفن شما"/>
        </label>
    </div>
</fieldset>

<fieldset class="grid grid-cols-2 gap-6 border p-5 my-3">
    <legend>سر فصل</legend>

    <div class="col-span-2 md:col-span-1">
        <label id="cust_subject" class="form-control w-full">
            <select id="cust_subject" name="cust_subject" class="select select-bordered">
                <option value="">طرف حساب ها</option>
                @foreach ($subjects as $subject)
                    <option
                        value="{{ $subject->id }}" {{ (isset($configs['cust_subject']) && $configs['cust_subject'] == $subject->id) ? 'selected' : '' }}>
                        {{ $subject->name }}
                    </option>
                @endforeach
            </select>
        </label>
    </div>

    <div class="col-span-2 md:col-span-1">
        <label for="bank" class="form-control w-full">
            <select id="bank" name="bank" class="select select-bordered">
                <option value="">بانک ها</option>
                @foreach ($banks as $bank)
                    <option
                        value="{{ $bank->id }}" {{ (isset($configs['bank']) && $configs['bank'] == $bank->id) ? 'selected' : '' }}>
                        {{ $bank->name }}
                    </option>
                @endforeach
            </select>
        </label>
    </div>
    <div class="col-span-2 md:col-span-1">
        <label for="cash" class="input input-bordered flex items-center gap-2">
            نقدی
            <input type="text" id="cash" name="cash"
                   class="grow" value="{{ old('cash', $configs['cash'] ?? '') }}"
                   placeholder="نقدی"/>
        </label>
    </div>
    <div class="col-span-2 md:col-span-1">
        <label for="buy_discount" class="input input-bordered flex items-center gap-2">
            تخفیفات خرید
            <input type="text" id="buy_discount" name="buy_discount"
                   class="grow" value="{{ old('buy_discount', $configs['buy_discount'] ?? '') }}"
                   placeholder="تخفیفات خرید"/>
        </label>
    </div>
    <div class="col-span-2 md:col-span-1">
        <label for="sell_discount" class="input input-bordered flex items-center gap-2">
            تخفیفات فروش
            <input type="text" id="sell_discount" name="sell_discount"
                   class="grow" value="{{ old('sell_discount', $configs['sell_discount']  ?? '') }}"
                   placeholder="تخفیفات فروش"/>
        </label>
    </div>
    <div class="col-span-2 md:col-span-1">
        <label for="sell_vat" class="input input-bordered flex items-center gap-2">
            مالیات فروش
            <input type="text" id="sell_vat" name="sell_vat"
                   class="grow" value="{{ old('sell_vat', $configs['sell_vat']  ?? '') }}"
                   placeholder="مالیات فروش"/>
        </label>
    </div>
    <div class="col-span-2 md:col-span-1">
        <label for="buy_vat" class="input input-bordered flex items-center gap-2">
            مالیات خرید
            <input type="text" id="buy_vat" name="buy_vat"
                   class="grow" value="{{ old('buy_vat', $configs['buy_vat']  ?? '') }}"
                   placeholder="مالیات خرید"/>
        </label>
    </div>

    <div class="col-span-2 md:col-span-1">
        <label for="sell_free" class="input input-bordered flex items-center gap-2">
            عوارض فروش
            <input type="text" id="sell_free" name="sell_free"
                   class="grow" value="{{ old('sell_free', $configs['sell_free']  ?? '') }}"
                   placeholder="عوارض فروش"/>
        </label>
    </div>
</fieldset>

