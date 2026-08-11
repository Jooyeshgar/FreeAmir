@props([
    'id',
    'export',
    'filters' => [],
    'label' => null,
    'class' => 'btn btn-sm btn-outline',
])

<button type="button" class="{{ $class }}" onclick="document.getElementById('{{ $id }}').showModal()">{{ $label ?? __('Get export') }}</button>

<dialog id="{{ $id }}" class="modal">
    <div class="modal-box max-w-md">
        <h3 class="text-lg font-bold">{{ __('How would you like to receive this file?') }}</h3>
        <p class="mt-2 text-sm text-base-content/70">{{ __('Download it now or send it to :email.', ['email' => auth()->user()->email]) }}</p>

        <div class="modal-action">
            <form method="dialog">
                <button class="btn btn-ghost">{{ __('Cancel') }}</button>
            </form>
            <form action="{{ route('send-to-email') }}" method="POST" class="inline-flex gap-2">
                @csrf
                <input type="hidden" name="export" value="{{ $export }}">
                @foreach ($filters as $key => $value)
                    @if (is_array($value))
                        @foreach ($value as $item)
                            <input type="hidden" name="filters[{{ $key }}][]" value="{{ $item }}">
                        @endforeach
                    @elseif (! is_null($value))
                        <input type="hidden" name="filters[{{ $key }}]" value="{{ $value }}">
                    @endif
                @endforeach
                <button type="submit" name="delivery" value="download" class="btn btn-primary">{{ __('Download') }}</button>
                <button type="submit" name="delivery" value="email" class="btn btn-secondary">{{ __('Send to email') }}</button>
            </form>
        </div>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button aria-label="{{ __('Close') }}"></button>
    </form>
</dialog>
