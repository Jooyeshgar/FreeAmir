<label {{ $attributes->whereDoesntStartWith('@')->merge(['class' => 'flex flex-col flex-wrap ' . $attributes->get('label_class')]) }}>
    <span {{ $attributes->whereDoesntStartWith('@')->merge(['class' => $attributes->get('label_text_class')]) }}>
        {{ $attributes->get('title') }}
    </span>
    <input {{ $attributes->whereStartsWith('@')->merge() }} onkeyup="{{ $attributes->get('onkeyup_input') }}"
        {{ $attributes->get('disabled') ? 'disabled' : '' }} id="{{ $attributes->get('id_input') }}"
        name="{{ $attributes->get('input_name') }}" value="{{ $attributes->get('input_value') }}"
        {{ $attributes->merge(['class' => 'max-h-10 input input-bordered border-slate-400 disabled:background-slate-700 w-full max-w-42 ' . $attributes->get('input_class')]) }}
        type="text" placeholder=" {{ $attributes->get('placeholder') }}" />
</label>
