<fieldset class="grid grid-cols-2 gap-6 border p-5 my-3">
    <legend>حساب</legend>
    <div class="col-span-2 md:col-span-1">
        <label id="name" class="input input-bordered flex items-center gap-2">
            نام
            <input type="text" id="name" name="name"
                   class="grow" value="{{ old('name', $bankAccount->name ?? '') }}"
                   placeholder="نام" required/>
        </label>
    </div>

    <div class="col-span-2 md:col-span-1">
        <label id="number" class="input input-bordered flex items-center gap-2">
            شماره
            <input type="number" id="number" name="number"
                   class="grow" value="{{ old('number', $bankAccount->number ?? '') }}"
                   placeholder="شماره" required/>
        </label>
    </div>

    <div class="col-span-2 md:col-span-1">
        <label id="type" class="input input-bordered flex items-center gap-2">
            نوع
            <input type="number" id="type" name="type"
                   class="grow" value="{{ old('type', $bankAccount->type ?? '') }}"
                   placeholder="نوع"/>
        </label>
    </div>

    <div class="col-span-2 md:col-span-1">
        <label id="owner" class="input input-bordered flex items-center gap-2">
            صاحب
            <input type="text" id="owner" name="owner"
                   class="grow" value="{{ old('owner', $bankAccount->owner ?? '') }}"
                   placeholder="صاحب" required/>
        </label>
    </div>

    <div class="col-span-2">

        <label id="desc" class="form-control">
            <span class="label">
                <span class="label-text">توضیح</span>
            </span>
            <textarea id="desc" name="desc"
                      class="textarea textarea-bordered h-24" placeholder="توضیح">{{ old('desc', $bankAccount->desc ?? '') }}</textarea>
        </label>

    </div>
</fieldset>

<fieldset class="grid grid-cols-2 gap-6 border p-5">
    <legend>بانک</legend>
    <div class="col-span-2 md:col-span-1">
        <label id="bank_id" class="form-control w-full">
            <select id="bank_id" name="bank_id" class="select select-bordered" required>
                <option value="">بانک</option>
                @foreach ($banks as $bank)
                    <option
                        value="{{ $bank->id }}" {{ (isset($bankAccount) && $bankAccount->bank_id == $bank->id) ? 'selected' : '' }}>
                        {{ $bank->name }}
                    </option>
                @endforeach
            </select>
        </label>
    </div>
    <div class="col-span-2 md:col-span-1">
        <label id="bank_branch" class="input input-bordered flex items-center gap-2">
            شعبه
            <input type="text" id="bank_branch" name="bank_branch"
                   class="grow" value="{{ old('bank_branch', $bankAccount->bank_branch ?? '') }}"
                   placeholder="شعبه"/>
        </label>
    </div>

    <div class="col-span-2 md:col-span-1">
        <label id="bank_phone" class="input input-bordered flex items-center gap-2">
            تلفن
            <input type="text" id="bank_phone" name="bank_phone"
                   class="grow" value="{{ old('bank_phone', $bankAccount->bank_phone ?? '') }}"
                   placeholder="تلفن"/>
        </label>
    </div>

    <div class="col-span-2">
        <label id="bank_address" class="form-control">
            <span class="label">
                <span class="label-text">نشانی</span>
            </span>
            <textarea id="bank_address" name="bank_address"
                      class="textarea textarea-bordered h-24" placeholder="نشانی">{{ old('bank_address', $bankAccount->bank_address ?? '') }}</textarea>
        </label>
    </div>

    <div class="col-span-2 md:col-span-1">
        <label id="bank_web_page" class="input input-bordered flex items-center gap-2">
            صفحه وب
            <input type="text" id="bank_web_page" name="bank_web_page"
                   class="grow" value="{{ old('bank_web_page', $bankAccount->bank_web_page ?? '') }}"
                   placeholder="صفحه وب"/>
        </label>
    </div>

</fieldset>
