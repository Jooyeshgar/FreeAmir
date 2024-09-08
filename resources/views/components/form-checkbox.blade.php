<div class="mx-0.5">
    <label class="form-control w-full flex items-center">
        <input type="checkbox" name="{{ $name }}" class="checkbox" {{ old($name, $checked) ? 'checked' : '' }} />
        <span class="label-text ml-2">{{ $title }}</span>
        @if ($errors->first($name))
            <div class="label">
                <span class="label-text-alt text-red-700">{{ $errors->first($name) }}</span>
            </div>
        @endif
    </label>
</div>
