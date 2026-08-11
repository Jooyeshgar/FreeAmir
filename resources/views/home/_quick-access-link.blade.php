<a href="{{ $link['href'] }}" data-home-area="{{ $link['area'] }}"
    class="group flex min-h-20 items-center gap-2 rounded-2xl border p-2 text-sm font-semibold shadow-sm transition duration-300 hover:-translate-y-0.5 hover:shadow-md {{ $link['style'] }}">
    <span class="inline-flex items-center gap-2">
        <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-xl bg-base-100/70">
            <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 12h14m-5-5 5 5-5 5" />
            </svg>
        </span>
        {{ $link['label'] }}
    </span>
</a>
