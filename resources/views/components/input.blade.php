@props([
    'id',
    'name',
    'title' => '',
    'type' => 'text',
    'placeholder' => false,
    'value' => false,
    'hint' => false,
    'title2' => false,
    'hint2' => false,
    'disabled' => false,
    'required' => false,
    'bordered' => true,
    'model_name' => false,
])
<label {{ $attributes->whereDoesntStartWith('x-')->merge(['class' => 'form-control w-full ']) }}>
    @if ($title != '')
        <div class="label">
            <span class="label-text">{{ $title }}{{ $required ? '*' : '' }}</span>
            @if ($title2)
                <span class="label-text-alt">{!! $title2 !!}</span>
            @endif
        </div>
    @endif
    <input {{ $attributes->whereStartsWith('x-')->merge() }} title="{{ $title }}" type="{{ $type }}" name="{{ $name }}" id="{{ $name }}"
        value="{{ $value ?? '' }}" placeholder="{{ $placeholder ?? '' }}" class="input {{ $bordered ? 'input-bordered' : '' }} w-full max-w-full max-h-10"
        {{ $required ? 'required' : '' }} {{ $disabled ? 'disabled' : '' }} {!! $model_name ? "x-model=\"$model_name\"" : '' !!} />

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
