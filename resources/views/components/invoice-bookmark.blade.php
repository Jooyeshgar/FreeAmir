@props(['invoice'])

@canany(['invoices.index', 'invoices.show', 'invoices.*'])
    <form action="{{ route('invoices.bookmark', $invoice) }}" method="POST" class="inline-flex">
        @csrf
        @method('PATCH')
        <input type="hidden" name="is_bookmarked" value="{{ $invoice->is_bookmarked ? '0' : '1' }}">
        <button type="submit" class="btn btn-sm btn-ghost btn-square {{ $invoice->is_bookmarked ? 'text-warning' : '' }}"
            aria-pressed="{{ $invoice->is_bookmarked ? 'true' : 'false' }}"
            aria-label="{{ $invoice->is_bookmarked ? __('Remove invoice bookmark') : __('Bookmark invoice') }}"
            title="{{ $invoice->is_bookmarked ? __('Remove invoice bookmark') : __('Bookmark invoice') }}">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24"
                fill="{{ $invoice->is_bookmarked ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="m12 3 2.78 5.63L21 9.54l-4.5 4.39 1.06 6.2L12 17.2l-5.56 2.93 1.06-6.2L3 9.54l6.22-.91L12 3Z" />
            </svg>
        </button>
    </form>
@endcanany
