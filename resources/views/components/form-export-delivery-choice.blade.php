@props([
    'id',
    'form',
    'export',
    'label' => null,
    'class' => 'btn btn-primary',
])

<button type="button" class="{{ $class }}" onclick="document.getElementById('{{ $id }}').showModal()">
    {{ $label ?? __('Receive Report') }}
</button>

<dialog id="{{ $id }}" class="modal">
    <div class="modal-box max-w-md">
        <h3 class="text-lg font-bold">{{ __('Receive Report') }}</h3>

        <div class="mt-4">
            <label class="fieldset w-full">
                <span class="label">{{ __('Recipient email') }}</span>
                <input type="email" name="email" value="{{ old('email', auth()->user()->email) }}" required maxlength="255"
                    class="input input-bordered w-full" dir="ltr" autocomplete="email" form="{{ $form }}">
            </label>
            <p class="mt-2 text-sm text-base-content/70">
                {{ __('You can replace your email address to send this report to someone else. The email will identify you as the requester.') }}
            </p>
        </div>

        <input type="hidden" name="export" value="{{ $export }}" form="{{ $form }}">
        <div class="modal-action">
            <button type="button" class="btn btn-ghost" onclick="document.getElementById('{{ $id }}').close()">{{ __('Cancel') }}</button>
            <button type="submit" form="{{ $form }}" name="_token" value="{{ csrf_token() }}" formaction="{{ route('send-to-email', ['delivery' => 'download']) }}" formmethod="POST" formtarget="_self" formnovalidate class="btn btn-primary">
                {{ __('Download') }}
            </button>
            <button type="submit" form="{{ $form }}" name="_token" value="{{ csrf_token() }}" formaction="{{ route('send-to-email', ['delivery' => 'email']) }}" formmethod="POST" formtarget="_self" class="btn btn-secondary">
                {{ __('Send to email') }}
            </button>
        </div>
    </div>
    <div class="modal-backdrop">
        <button type="button" aria-label="{{ __('Close') }}" onclick="document.getElementById('{{ $id }}').close()"></button>
    </div>
</dialog>
