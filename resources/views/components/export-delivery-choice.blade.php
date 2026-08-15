@props([
    'id',
    'export',
    'filters' => [],
    'form' => null,
    'label' => null,
    'class' => 'btn btn-sm btn-outline',
])

<button type="button" {{ $attributes->merge(['class' => $class]) }} onclick="document.getElementById('{{ $id }}').showModal()">{{ $label ?? __('Receive Report') }}</button>

<dialog id="{{ $id }}" class="modal">
    <div class="modal-box max-w-md">
        <h3 class="text-lg font-bold">{{ __('Receive Report') }}</h3>

        @if ($form)
            <div class="mt-4">
        @else
            <form action="{{ route('send-to-email') }}" method="POST" class="mt-4">
                @csrf
        @endif
            <label class="fieldset w-full">
                <span class="label">{{ __('Recipient email') }}</span>
                <input type="email" name="email" value="{{ old('email', auth()->user()->email) }}" required maxlength="255"
                    class="input input-bordered w-full" dir="ltr" autocomplete="email" @if ($form) form="{{ $form }}" @endif>
            </label>
            <p class="mt-2 text-sm text-base-content/70">
                {{ __('You can replace your email address to send this report to someone else. The email will identify you as the requester.') }}
            </p>

            <input type="hidden" name="export" value="{{ $export }}" @if ($form) form="{{ $form }}" @endif>
            @unless ($form)
                @foreach ($filters as $key => $value)
                    @if (is_array($value))
                        @foreach ($value as $item)
                            <input type="hidden" name="filters[{{ $key }}][]" value="{{ $item }}">
                        @endforeach
                    @elseif (! is_null($value))
                        <input type="hidden" name="filters[{{ $key }}]" value="{{ $value }}">
                    @endif
                @endforeach
            @endunless
            <div class="modal-action">
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('{{ $id }}').close()">{{ __('Cancel') }}</button>
                <button type="submit"
                    @if ($form)
                        form="{{ $form }}" name="_token" value="{{ csrf_token() }}"
                        formaction="{{ route('send-to-email', ['delivery' => 'download']) }}" formmethod="POST" formtarget="_self"
                    @else
                        name="delivery" value="download"
                    @endif
                    formnovalidate class="btn btn-primary">
                    {{ __('Download') }}
                </button>
                <button type="submit"
                    @if ($form)
                        form="{{ $form }}" name="_token" value="{{ csrf_token() }}"
                        formaction="{{ route('send-to-email', ['delivery' => 'email']) }}" formmethod="POST" formtarget="_self"
                    @else
                        name="delivery" value="email"
                    @endif
                    class="btn btn-secondary">
                    {{ __('Send to email') }}
                </button>
            </div>
        @if ($form)
            </div>
        @else
            </form>
        @endif
    </div>
    <div class="modal-backdrop">
        <button type="button" aria-label="{{ __('Close') }}" onclick="document.getElementById('{{ $id }}').close()"></button>
    </div>
</dialog>
