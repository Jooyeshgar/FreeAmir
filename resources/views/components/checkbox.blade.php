<div class="form-control">
    <label class="label cursor-pointer">
        <span class="label-text">{{ $title }}</span>
        <input name="{{ $name }}" type="checkbox" @if ($checked == 1) checked="checked" @endif class="checkbox"
            @if ($value != 0) value="{{ $value }}" @endif />
    </label>
</div>
