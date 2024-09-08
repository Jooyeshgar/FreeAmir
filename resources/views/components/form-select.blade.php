<div class="mx-0.5">

    <label class="form-control w-full">
        <div class="label {{ !$title ? 'hidden' : '' }}">
            <span class="label-text">{{ $title ?? '' }}</span>
        </div>
        <select name="{{ is_array($selected) ? $name . '[]' : $name }}" id="{{ $name }}"
            class="select select-bordered w-full" style="outline: none !important;" {{ is_array($selected) ? 'multiple' : '' }}>
            @foreach($options as $value => $label)
                <option value="{{ $value }}" {{ (is_array(old($name, $selected)) && in_array($value, old($name, $selected))) || old($name, $selected) == $value ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        @if ($errors->first($name))
            <div class="label ">
                <span class="label-text-alt text-red-700">{{ $errors->first($name) }}</span>
            </div>
        @endif
    </label>
</div>