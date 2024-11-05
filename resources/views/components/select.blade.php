@props([
    'title',
    'name',
    'id',
    'title2' => null,
    'hint' => null,
    'hint2' => null,
    'options' => [],
    'selected' => null,
])

<label {{ $attributes->merge(['class' => 'form-control w-full ' . $attributes->get('class')]) }}>

    <div class='label'>
        <span class="label-text">{{ $title }}</span>
        @if ($title2)
            <span class="label-text-alt">{{ $title2 }}</span>
        @endif
    </div>

    <select name="{{ $name }}" id="{{ $id }}" class= 'select select-bordered '>

        @foreach ($options as $key => $value)
            <option value="{{ $key }}"
                {{ old($name, $selected == $key || $selected == $value) ? 'selected' : '' }}>
                {{ $value }}
            </option>
        @endforeach
    </select>
    @if ($errors->first($name))
        <span class="label-text-alt text-rose-700">{{ $errors->first($name) }}</span>
    @else
        @if ($hint || $hint2)
            <div class='label'>
                @if ($hint)
                    <span class="label-text-alt">{!! $hint !!}</span>
                @endif
                @if ($hint2)
                    <span class="label-text-alt">{!! $hint2 !!}</span>
                @endif
            </div>
        @endif
    @endif
</label>
