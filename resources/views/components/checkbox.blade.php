@props(['title', 'name', 'id', 'value' => 1, 'checked' => false])

<div class="fieldset">
    <label class="label cursor-pointer justify-start gap-3">
        <span>{{ $title }}</span>
        <input {{ $attributes->merge() }} name="{{ $name }}" id="{{ $id }}"
            type="checkbox" {{ $checked ? 'checked' : '' }} class="checkbox" value="{{ $value }}" />
    </label>
</div>
