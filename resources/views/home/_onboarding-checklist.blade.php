<section
    x-data="{
        sessionKey: 'freeamir_onboarding_hidden_{{ (int) auth()->id() }}',
        collapsed: false,
        menuOpen: false,
        dismissed: false,
        dismissing: false,
        init() {
            try {
                if (sessionStorage.getItem(this.sessionKey) === '1') {
                    this.dismissed = true;
                }
            } catch (error) {
                // Ignore storage errors in restricted browser contexts.
            }
        },
        hideForNow() {
            try {
                sessionStorage.setItem(this.sessionKey, '1');
            } catch (error) {
                // Ignore storage errors in restricted browser contexts.
            }
            this.menuOpen = false;
            this.dismissed = true;
        },
        async dismissPermanently() {
            if (this.dismissing) {
                return;
            }
            this.dismissing = true;
            try {
                const token = document.querySelector('meta[name=csrf_token]')?.getAttribute('content') || '';
                const response = await fetch(@js($onboarding['dismissUrl']), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                if (response.ok) {
                    this.menuOpen = false;
                    this.dismissed = true;
                }
            } finally {
                this.dismissing = false;
            }
        },
    }"
    x-show="!dismissed"
    x-transition.opacity.duration.200ms
    aria-labelledby="onboarding-checklist-title"
    data-onboarding-checklist
    class="relative rounded-3xl border border-base-300 bg-base-100 p-4 shadow-sm sm:p-5"
>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex min-w-0 items-center gap-2.5">
            <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-xl bg-info/15 text-info">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </span>
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 id="onboarding-checklist-title" class="text-sm font-bold text-base-content sm:text-base">
                        {{ __('Getting started') }}
                    </h2>
                    <span class="badge badge-sm border-info/25 bg-info/10 font-semibold text-info">
                        {{ __(':completed of :total steps completed', ['completed' => formatNumber($onboarding['completedCount']), 'total' => formatNumber($onboarding['totalCount'])]) }}
                    </span>
                </div>
                <p class="mt-0.5 text-xs text-base-content/60">
                    {{ __('Complete these quick steps to set up your workspace.') }}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-1">
            <button
                type="button"
                @click="collapsed = !collapsed"
                :aria-expanded="(!collapsed).toString()"
                :title="collapsed ? @js(__('Show checklist')) : @js(__('Hide checklist'))"
                :aria-label="collapsed ? @js(__('Show checklist')) : @js(__('Hide checklist'))"
                class="btn btn-ghost btn-xs btn-square text-base-content/60 hover:text-base-content"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="size-4 transition-transform duration-200" :class="collapsed ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m4.5 15.75 7.5-7.5 7.5 7.5" />
                </svg>
            </button>

            <div class="relative" @click.outside="menuOpen = false" @keydown.escape.window="menuOpen = false">
                <button
                    type="button"
                    @click="menuOpen = !menuOpen"
                    :aria-expanded="menuOpen.toString()"
                    aria-haspopup="menu"
                    title="{{ __('Close') }}"
                    aria-label="{{ __('Close') }}"
                    data-onboarding-close-menu
                    class="btn btn-ghost btn-xs btn-square text-base-content/50 hover:text-base-content"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>

                <div
                    x-show="menuOpen"
                    x-cloak
                    x-transition.opacity.duration.150ms
                    role="menu"
                    class="absolute end-0 top-full z-20 mt-1.5 w-48 rounded-xl border border-base-300 bg-base-100 p-1.5 shadow-lg"
                >
                    <button
                        type="button"
                        role="menuitem"
                        @click="hideForNow()"
                        data-onboarding-hide-now
                        class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-start text-xs font-medium text-base-content transition-colors hover:bg-base-200"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5 shrink-0 text-base-content/60" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                        </svg>
                        <span>{{ __('Hide for now') }}</span>
                    </button>

                    <form method="POST" action="{{ $onboarding['dismissUrl'] }}" @submit.prevent="dismissPermanently()">
                        @csrf
                        <button
                            type="submit"
                            role="menuitem"
                            :disabled="dismissing"
                            data-onboarding-dismiss
                            class="mt-0.5 flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-start text-xs font-medium text-error transition-colors hover:bg-error/10"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" />
                            </svg>
                            <span>{{ __("Don't show again") }}</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div
        x-show="!collapsed"
        x-transition
        class="onboarding-steps-grid mt-4"
        style="--onboarding-cols: {{ count($onboarding['steps']) }};"
        role="list"
        aria-label="{{ __('Getting started') }}"
    >
        @foreach ($onboarding['steps'] as $step)
            @php
                $stateClass = $step['completed']
                    ? 'is-done'
                    : ($step['current'] ? 'is-current' : 'is-pending');
            @endphp
            <div class="onboarding-step-col" role="listitem" data-onboarding-step="{{ $step['key'] }}" data-onboarding-state="{{ $step['completed'] ? 'completed' : ($step['current'] ? 'current' : 'pending') }}">
                <div class="onboarding-arrow {{ $stateClass }}">
                    @if ($step['completed'])
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                    @else
                        <span class="onboarding-step-index" aria-hidden="true">{{ formatNumber($loop->iteration) }}</span>
                    @endif
                    <span class="truncate">{{ $step['title'] }}</span>
                </div>

                <div class="onboarding-step-action">
                    @if ($step['completed'])
                        <span class="inline-flex items-center gap-1 text-[11px] font-medium text-emerald-600 dark:text-emerald-400">
                            {{ __('Completed') }}
                        </span>
                    @elseif ($step['primaryAction'])
                        <a
                            href="{{ $step['primaryAction']['url'] }}"
                            class="btn btn-xs {{ $step['current'] ? 'btn-info' : 'btn-ghost border border-base-300 bg-base-100 hover:border-info/40 hover:bg-info/10 hover:text-info' }} gap-1 font-medium"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            <span>{{ $step['primaryAction']['label'] }}</span>
                        </a>

                        @if ($step['secondaryAction'])
                            <a
                                href="{{ $step['secondaryAction']['url'] }}"
                                class="text-[11px] font-medium text-base-content/60 underline-offset-2 transition-colors hover:text-info hover:underline"
                            >
                                {{ $step['secondaryAction']['label'] }}
                            </a>
                        @endif
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</section>
