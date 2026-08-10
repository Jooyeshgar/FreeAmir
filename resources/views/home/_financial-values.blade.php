@can('home.summary')
    @if (($hasBusinessPerms && ($canFinancial || $canSales || $canInventory)) || ($canSeePersonalPortal && $hasPersonalData))
        <section aria-labelledby="private-summary-title" class="relative overflow-hidden rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm sm:p-6">
            <div aria-hidden="true" class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-primary via-success to-info"></div>
            <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 id="private-summary-title" class="text-base font-bold text-base-content">{{ __('Financial values') }}</h2>
                    <p class="mt-1 text-xs leading-5 text-base-content/55">
                        {{ __('Financial values stay hidden until you reveal them.') }}
                    </p>
                </div>
                <span class="mt-2 inline-flex w-fit items-center gap-1.5 rounded-full bg-base-200 px-2.5 py-1 text-xs text-base-content/60 sm:mt-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 15v2m-6 4h12a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2Zm10-10V7a4 4 0 0 0-8 0v4" />
                    </svg>
                    {{ __('Reveal one value at a time') }}
                </span>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 {{ $privateMetricGridClass }}">
                @foreach ($basePrivateMetrics as $metric)
                    @include('home._private-metric-card', compact('metric', 'privateMetricThemes'))
                @endforeach

                @foreach ($rolePrivateMetrics as $metric)
                    @include('home._private-metric-card', compact('metric', 'privateMetricThemes'))
                @endforeach
            </div>
        </section>
    @endif
@endcan
