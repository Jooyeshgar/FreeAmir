<div class="mx-0.5">
    <label class="form-control w-full max-w-xs">
        <div class="label">
            <span class="label-text">{{ $title }}</span>
        </div>
        <input type="{{ $type }}" name="{{ $name }}" placeholder="{{ $placeHolder }}" class="input input-bordered w-full max-w-xs"
            value="{{ $value }}" />
        @if ($errors->first($name))
            <div class="label">
                <span class="label-text-alt text-red-700">{{ $errors->first($name) }}</span>
            </div>
        @endif
    </label>
</div>
