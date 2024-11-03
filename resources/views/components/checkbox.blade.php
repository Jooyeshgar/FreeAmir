@props(['title', 'name', 'id', 'checked' => false])

<div class="form-control">
    <label class="label cursor-pointer">
        <span class="label-text">{{ $title }}</span>
        <input name="{{ $name }}" id="{{ $id }}" type="checkbox" {{ $checked ? 'checked' : '' }}
            class="checkbox" value="1" />
    </label>
</div>
