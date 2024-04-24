<div>
    <label class="form-control w-full max-w-xs">
        <div class="label">
            <span class="label-text">{{ $title }}</span>
        </div>
        <input type="text" name="{{ $name }}" placeholder="{{ $placeHolder }}"
            class="input input-bordered w-full max-w-xs" />
        @if ($message)
            <div class="label">
                <span class="label-text-alt text-red-700">{{ $message }}</span>
            </div>
        @endif
    </label>
</div>
