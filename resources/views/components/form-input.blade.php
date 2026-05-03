<div class="mx-0.5">
    <fieldset class="fieldset w-full max-w-xs">
        <label for="{{ $name }}" class="fieldset-legend">{{ $title }}</label>
        <input type="{{ $type }}" name="{{ $name }}" id="{{ $name }}" placeholder="{{ $placeHolder }}" class="input w-full max-w-xs"
            value="{{ $value }}" />
        @if ($errors->first($name))
            <div class="label">
                <span class="text-xs text-red-700">{{ $errors->first($name) }}</span>
            </div>
        @endif
    </fieldset>
</div>
