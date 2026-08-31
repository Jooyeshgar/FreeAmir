@props(['withinStickyHeader' => false])

@php($impersonateManager = app(\Lab404\Impersonate\Services\ImpersonateManager::class))

@if (auth()->user() && $impersonateManager->isImpersonating())
    <aside @class([
        'z-20 w-full border-b border-amber-300 bg-amber-100 text-amber-950 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100',
        'sticky top-0' => ! $withinStickyHeader,
    ]) role="status">
        <div class="mx-auto flex min-h-12 w-full flex-col items-stretch gap-2 px-3 py-2 sm:flex-row sm:items-center sm:justify-between min-[1430px]:w-[1430px]">
            <p class="min-w-0 text-sm font-semibold">
                {{ __('You are impersonating :name.', ['name' => auth()->user()->name]) }}
            </p>
            <form action="{{ route('impersonation.leave') }}" method="post">
                @csrf
                <button type="submit" class="btn btn-warning min-h-11 whitespace-nowrap btn-sm">
                    {{ __('Return to administrator') }}
                </button>
            </form>
        </div>
    </aside>
@endif
