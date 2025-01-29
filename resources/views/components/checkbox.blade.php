@props(['title', 'name', 'id', 'value' => 1, 'checked' => false])

<div class="form-control">
    <label class="label cursor-pointer">
        <span class="label-text">{{ $title }}</span>
        <input {{ $attributes->merge() }} name="{{ $name }}" id="{{ $id }}"
            type="checkbox" {{ $checked ? 'checked' : '' }} class="checkbox" value="{{ $value }}" />
    </label>
</div>
