@props(['name', 'title' => '', 'accept' => '', 'required' => false])

<fieldset {{ $attributes->merge(['class' => 'form-control w-full']) }}>
    @if ($title != '')
        <span class="label">{{ $title }}{{ $required ? '*' : '' }}</span>
    @endif
    <input type="file" id="{{ $name }}" name="{{ $name }}"
        class="file-input w-full max-w-full @error($name) file-input-error @enderror"
        @if ($accept) accept="{{ $accept }}" @endif {{ $required ? 'required' : '' }} />
    @if ($errors->first($name))
        <span class="label text-xs text-rose-700">{{ $errors->first($name) }}</span>
    @endif
</fieldset>
