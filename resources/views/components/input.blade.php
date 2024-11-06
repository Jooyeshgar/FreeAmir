@props([
    'id',
    'name',
    'title',
    'type' => 'text',
    'placeholder' => false,
    'value' => false,
    'hint' => false,
    'title2' => false,
    'hint2' => false,
    'disabled' => false,
    'required' => false,
])
<label {{ $attributes->merge(['class' => 'form-control w-full ' . $attributes->get('class')]) }}>

    <div class="label">
        <span class="label-text">{{ $title }} {{ $required ? '*' : '' }}</span>
        @if ($title2)
            <span class="label-text-alt">{{ $title2 }}</span>
        @endif
    </div>

    <input title="{{ $title }}" type="{{ $type }}" name="{{ $name }}" id="{{ $name }}"
        value="{{ $value ?? '' }}" placeholder="{{ $placeholder ?? '' }}" class="input input-bordered w-full max-w-full"
        {{ $required ? 'required' : '' }} {{ $disabled ? 'disabled' : '' }} />

    @if ($errors->first($name))
        <span class="label-text-alt text-rose-700">{{ $errors->first($name) }}</span>
    @else
        @if ($hint || $hint2)
            <div class="label">
                @if ($hint)
                    <span class="label-text-alt">{{ $hint }}</span>
                @endif
                @if ($hint2)
                    <span class="label-text-alt">{{ $hint2 }}</span>
                @endif
            </div>

        @endif
    @endif

</label>
