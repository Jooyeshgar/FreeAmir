<div class="grid grid-cols-2 gap-6">
    <div class="col-span-2 md:col-span-1">


        <label id="group" class="form-control w-full">
            <select id="group" name="group" class="select select-bordered" required>
                <option value="">گروه کالا</option>
                @foreach ($groups as $group)
                    <option
                        value="{{ $group->id }}" {{ (isset($product) && $product->group == $group->id) ? 'selected' : '' }}>
                        {{ $group->name }}
                    </option>
                @endforeach
            </select>
        </label>
        <a class="link text-blue-500" href="{{ route('product-groups.create') }}">اضافه کردن کالا</a>
    </div>

    <div class="col-span-2 md:col-span-1">
        <label id="code" class="input input-bordered flex items-center gap-2">
            کد
            <input type="text" id="code" name="code"
                   class="grow" value="{{ old('code', $product->code ?? '') }}"
                   placeholder="کد" required/>
        </label>
    </div>

    <div class="col-span-2 md:col-span-1">
        <label id="location" class="input input-bordered flex items-center gap-2">
            موقعیت در انبار
            <input type="text" id="location" name="location"
                   class="grow" value="{{ old('location', $product->location ?? '') }}"
                   placeholder="موقعیت در انبار"/>
        </label>
    </div>

    <div class="col-span-2 md:col-span-1">
        <label id="name" class="input input-bordered flex items-center gap-2">
            نام محصول
            <input type="text" id="name" name="name"
                   class="grow" value="{{ old('name', $product->name ?? '') }}"
                   placeholder="نام محصول" required/>
        </label>
    </div>

    <div class="col-span-2">

        <label id="description" class="form-control">
            <span class="label">
                <span class="label-text">توضیحات</span>
            </span>
            <textarea id="description" name="description"
                      class="textarea textarea-bordered h-24" placeholder="توضیحات">{{ old('description', $product->description ?? '') }}</textarea>
        </label>

    </div>

    <div class="flex items-center justify-between gap-5 col-span-2 md:col-span-1">
        <div class="flex items-center">
            <input {{ ( isset($product) && ($product->oversell == 1)) ? 'checked' : ''}} type="checkbox"
                   id="oversell" name="oversell"
                   class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
            <label for="oversell" class="block mb-2 text-sm font-medium text-gray-900 dark:text-gray-300">بیش
                فروش</label>
        </div>
        <div>
            <label id="quantity_warning" class="input input-bordered flex items-center gap-2">
                هشدار تعداد
                <input type="text" id="quantity_warning" name="quantity_warning"
                       class="grow" value="{{ old('quantity_warning', $product->quantity_warning ?? '') }}"
                       placeholder="هشدار تعداد"/>
            </label>
        </div>
    </div>

    <div class="col-span-2 md:col-span-1">
        <label id="quantity" class="input input-bordered flex items-center gap-2">
            موجودی اولیه
            <input type="text" id="quantity" name="quantity"
                   class="grow" value="{{ old('quantity', $product->quantity ?? '') }}"
                   placeholder="موجودی اولیه"/>
        </label>
    </div>

    <div class="col-span-2 md:col-span-1">
        <label id="selling_price" class="input input-bordered flex items-center gap-2">
            قیمت فروش
            <input type="text" id="selling_price" name="selling_price"
                   class="grow" value="{{ old('name', $product->selling_price ?? '') }}"
                   placeholder="قیمت فروش"/>
        </label>
    </div>

    <div class="col-span-2 md:col-span-1">
        <label id="purchace_price" class="input input-bordered flex items-center gap-2">
            قیمت خرید
            <input type="text" id="purchace_price" name="purchace_price"
                   class="grow" value="{{ old('purchace_price', $product->purchace_price ?? '') }}"
                   placeholder="قیمت خرید"/>
        </label>
    </div>

    <div class="col-span-2 md:col-span-1">
        <label id="discount_formula" class="input input-bordered flex items-center gap-2">
            فرمول تخفیف
            <input type="text" id="discount_formula" name="discount_formula"
                   class="grow" value="{{ old('discount_formula', $product->discount_formula ?? '') }}"
                   placeholder="فرمول تخفیف"/>
        </label>
    </div>
</div>

<div class="py-3">
    راهنمایی:از (مقدار) - (تا مقدار) : میزان  تخفیف  برای هر محصول مثال:
    1-30:400, 30-100:360.7, 100-170:300
</div>



