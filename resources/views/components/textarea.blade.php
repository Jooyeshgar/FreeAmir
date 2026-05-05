@props([
    'id',
    'name',
    'title',
    'placeholder' => false,
    'value' => false,
    'hint' => false,
    'title2' => false,
    'hint2' => false,
    'disabled' => false,
    'required' => false,
])

<fieldset {{ $attributes->merge(['class' => 'fieldset w-full ' . $attributes->get('class')]) }}>

    <div class="flex items-center justify-between gap-2">
        <label for="{{ $name }}" class="fieldset-legend">{{ $title }}{{ $required ? '*' : '' }}</label>
        @if ($title2)
            <span class="label text-xs">{{ $title2 }}</span>
        @endif
    </div>

    <textarea class="textarea h-auto w-full" placeholder="{{ $placeholder ?? '' }}"
        {{ $required ? 'required' : '' }} name="{{ $name }}" id="{{ $name }}">{{ $value ?? '' }}</textarea>

    @if ($errors->first($name))
        <span class="label text-xs text-rose-700">{{ $errors->first($name) }}</span>
    @else
        @if ($hint || $hint2)
            <div class="label">
                @if ($hint)
                    <span class="text-xs">{{ $hint }}</span>
                @endif
                @if ($hint2)
                    <span class="text-xs">{{ $hint2 }}</span>
                @endif
            </div>

        @endif
    @endif
</fieldset>
