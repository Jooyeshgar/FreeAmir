<div class="space-y-3 text-right" dir="rtl">
    @if ($activity['requestContext'])
        <dl class="mb-3 grid gap-2 text-xs sm:grid-cols-2 sm:gap-3 xl:grid-cols-5" dir="ltr">
            @foreach ($activity['requestContext'] as $context)
                <div class="rounded-xl bg-slate-50 p-3 text-left dark:bg-slate-950/40">
                    <dt class="text-right font-semibold text-slate-500" dir="rtl">{{ $context['label'] }}</dt>
                    <dd class="mt-1 break-all font-mono text-left" dir="ltr">{{ $context['value'] }}</dd>
                </div>
            @endforeach
        </dl>
    @endif
    @if ($activity['requestInput'])
        <button type="button" class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-2.5 py-1.5 text-[11px] font-semibold text-slate-600 ring-1 ring-inset ring-slate-200 transition hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700 dark:hover:bg-slate-700" @click="requestInputOpen = ! requestInputOpen" :aria-expanded="requestInputOpen" aria-controls="request-input-{{ $activity['id'] }}">{{ __('Request details') }}</button>
        <pre id="request-input-{{ $activity['id'] }}" x-cloak x-show="requestInputOpen" class="mt-2 max-h-72 overflow-auto rounded-xl bg-slate-950 p-3 text-xs text-slate-100" dir="ltr">{{ $activity['requestInput'] }}</pre>
    @endif
    @if (false && $activity['requestContext'])
        <dl class="grid gap-2 text-xs sm:grid-cols-2 sm:gap-3 xl:grid-cols-5">
            @foreach ($activity['requestContext'] as $context)
                <div class="rounded-xl bg-slate-50 p-3 text-right dark:bg-slate-950/40">
                    <dt class="font-semibold text-slate-500">{{ $context['label'] }}</dt>
                    <dd class="mt-1 break-all font-mono text-right" dir="ltr">{{ $context['value'] }}</dd>
                </div>
            @endforeach
        </dl>
    @endif
    @if (false && $activity['requestInput'])
        <button type="button"
            class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-2.5 py-1.5 text-[11px] font-semibold text-slate-600 ring-1 ring-inset ring-slate-200 transition hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700 dark:hover:bg-slate-700"
            @click="requestInputOpen = ! requestInputOpen" :aria-expanded="requestInputOpen"
            aria-controls="request-input-{{ $activity['id'] }}">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="1.8" class="h-3.5 w-3.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M7.5 8.25h9m-9 3.75h9m-9 3.75h5.25M5.25 3.75h13.5a1.5 1.5 0 0 1 1.5 1.5v13.5a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5V5.25a1.5 1.5 0 0 1 1.5-1.5Z" />
            </svg>
            {{ __('Request details') }}
        </button>
        <pre id="request-input-{{ $activity['id'] }}" x-cloak x-show="requestInputOpen"
            class="mt-2 max-h-72 overflow-auto rounded-xl bg-slate-950 p-3 text-xs text-slate-100" dir="ltr">{{ $activity['requestInput'] }}</pre>
    @endif
    @foreach ($activity['changes']->groupBy('model') as $model => $changes)
        <section class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800">
            <header
                class="flex flex-wrap items-center gap-2 border-b border-slate-200 bg-slate-50 px-3 py-2 text-right dark:border-slate-800 dark:bg-slate-950/40">
                <span class="text-[11px] font-semibold text-slate-500">{{ __('Affected model') }}</span>
                @if ($changes->first()['url'] ?? null)
                    <a href="{{ $changes->first()['url'] }}"
                        class="font-mono text-xs font-bold text-sky-700 hover:underline dark:text-sky-300"
                        dir="ltr">{{ $model }}</a>
                @else
                    <span class="font-mono text-xs font-bold text-sky-700 dark:text-sky-300"
                        dir="ltr">{{ $model }}</span>
                @endif
            </header>
            <div class="overflow-x-auto">
                <table class="table table-fixed table-sm w-full text-right">
                    <colgroup>
                        <col class="w-1/3">
                        <col class="w-1/3">
                        <col class="w-1/3">
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="text-right">{{ __('Field') }}</th>
                            <th class="text-right">{{ __('Previous value') }}</th>
                            <th class="text-right">{{ __('New value') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($changes as $change)
                            <tr class="border-b border-slate-200 last:border-b-0 dark:border-slate-800">
                                <td class="bg-slate-50 text-right font-mono text-xs font-semibold text-slate-700 dark:bg-slate-950/40 dark:text-slate-200"
                                    dir="ltr">{{ $change['field'] }}</td>
                                <td
                                    class="bg-rose-50/80 text-right font-normal text-rose-800 dark:bg-rose-950/40 dark:text-rose-200">
                                    <pre class="whitespace-pre-wrap break-all font-normal" dir="ltr">{{ $change['old'] }}</pre>
                                </td>
                                <td
                                    class="bg-emerald-50/80 text-right font-normal text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">
                                    <pre class="whitespace-pre-wrap break-all font-normal" dir="ltr">{{ $change['new'] }}</pre>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach
</div>
