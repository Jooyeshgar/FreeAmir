@if (($workInProgressItems ?? collect())->isNotEmpty())
    <section class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($workInProgressItems as $item)
            @php
                $tone = [
                    'warning' => ['card' => 'border-amber-400/30 bg-gradient-to-br from-amber-50 to-base-100 dark:from-amber-500/10', 'icon' => 'bg-amber-500/15 text-amber-600', 'badge' => 'badge-warning'],
                    'info' => ['card' => 'border-sky-400/30 bg-gradient-to-br from-sky-50 to-base-100 dark:from-sky-500/10', 'icon' => 'bg-sky-500/15 text-sky-600', 'badge' => 'badge-info'],
                    'success' => ['card' => 'border-emerald-400/30 bg-gradient-to-br from-emerald-50 to-base-100 dark:from-emerald-500/10', 'icon' => 'bg-emerald-500/15 text-emerald-600', 'badge' => 'badge-success'],
                ][$item['tone']];
            @endphp

            <a href="{{ $item['href'] }}" class="card border {{ $tone['card'] }} shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary/40">
                <div class="card-body flex-row items-center gap-4 p-4">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl {{ $tone['icon'] }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}" />
                        </svg>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-center justify-between gap-2">
                            <span class="font-semibold text-base-content">{{ $item['title'] }}</span>
                            <span class="badge {{ $tone['badge'] }} badge-lg">{{ $item['value'] }}</span>
                        </span>
                        <span class="mt-1 block text-sm text-base-content/60">{{ $item['description'] }}</span>
                    </span>
                </div>
            </a>
        @endforeach
    </section>
@endif
