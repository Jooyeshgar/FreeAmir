<div class="grid grid-cols-2 gap-5">
    <div class="col-span-2 md:col-span-1">
        <label for="code" class="input input-bordered flex items-center gap-2">
            {{ __('Account code') }}
            <input id="code" name="code" type="text" value="{{ old('code', $productGroup->code ?? '') }}" class="grow" placeholder="کد طرف حساب" />
        </label>
    </div>

    <div class="col-span-2 md:col-span-1">
        <label for="name" class="input input-bordered flex items-center gap-2">
            {{ __('Name') }}
            <input id="name" name="name" type="text" value="{{ old('name', $productGroup->name ?? '') }}" class="grow" placeholder="نام" />
        </label>
    </div>
</div>
