@props(['title', 'name', 'id', 'title2' => null, 'hint' => null, 'hint2' => null, 'options' => [], 'selected' => null])

<fieldset {{ $attributes->merge(['class' => 'fieldset w-full ' . $attributes->get('class')]) }}>

    <div class="flex items-center justify-between gap-2">
        <label for="{{ $id }}" class="fieldset-legend">{{ $title }}</label>
        @if ($title2)
            <span class="label text-xs">{!! $title2 !!}</span>
        @endif
    </div>

    <select name="{{ $name }}" id="{{ $id }}" class="select">

        @foreach ($options as $key => $value)
            <option value="{{ $key }}" {{ old($name, $selected == $key || $selected == $value) ? 'selected' : '' }}>
                {{ $value }}
            </option>
        @endforeach
    </select>
    @if ($errors->first($name))
        <span class="label text-xs text-rose-700">{{ $errors->first($name) }}</span>
    @else
        @if ($hint || $hint2)
            <div class='label'>
                @if ($hint)
                    <span class="text-xs">{!! $hint !!}</span>
                @endif
                @if ($hint2)
                    <span class="text-xs">{!! $hint2 !!}</span>
                @endif
            </div>
        @endif
    @endif
</fieldset>
