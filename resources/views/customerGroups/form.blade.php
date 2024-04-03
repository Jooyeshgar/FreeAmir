<div class="grid grid-cols-2 gap-6">

    <div class="col-span-2 md:col-span-1">
        <label id="code" class="input input-bordered flex items-center gap-2">
            کد
            <input type="text" id="code" name="code"
                   class="grow" value="{{ old('code', $customerGroup->code ?? '') }}"
                   placeholder="کد" required/>
        </label>
    </div>

    <div class="col-span-2 md:col-span-1">
        <label id="name" class="input input-bordered flex items-center gap-2">
            نام
            <input type="text" id="name" name="name"
                   class="grow" value="{{ old('name', $customerGroup->name ?? '') }}"
                   placeholder="نام" required/>
        </label>
    </div>

    <div class="col-span-2">

        <label id="description" class="form-control">
            <span class="label">
                <span class="label-text">توضیحات</span>
            </span>
            <textarea id="description" name="description"
                      class="textarea textarea-bordered h-24" placeholder="توضیحات">{{ old('name', $customerGroup->description ?? '') }}</textarea>
        </label>

    </div>

</div>




