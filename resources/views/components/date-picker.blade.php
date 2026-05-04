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
<fieldset {{ $attributes->whereDoesntStartWith('x-')->merge(['class' => 'fieldset w-full ']) }}>
    @if ($title != '')
        <div class="flex items-center justify-between gap-2">
            <label for="{{ $name }}" class="fieldset-legend">{{ $title }}{{ $required ? '*' : '' }}</label>
            @if ($title2)
                <span class="label text-xs">{!! $title2 !!}</span>
            @endif
        </div>
    @endif
    <input {{ $attributes->whereStartsWith('x-')->merge() }} data-jdp title="{{ $title }}" type="{{ $type }}" name="{{ $name }}"
        id="{{ $name }}" value="{{ $value ?? '' }}" placeholder="{{ $placeholder ?? '' }}"
        class="input {{ $bordered ? '' : 'input-ghost' }} w-full max-w-full max-h-10" {{ $required ? 'required' : '' }} {{ $disabled ? 'disabled' : '' }}
        {!! $model_name ? "x-model=\"$model_name\"" : '' !!} />

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
@pushOnce('scripts')
    <script type="module">
        jalaliDatepicker.startWatch();
    </script>
@endpushOnce
