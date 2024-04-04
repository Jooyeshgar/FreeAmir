<div class="col-span-2 md:col-span-1">
    <label id="name" class="input input-bordered flex items-center gap-2">
        نام
        <input type="text" id="name" name="name"
               class="grow" value="{{ old('name', $bank->name ?? '') }}"
               placeholder="نام" required/>
    </label>
</div>
