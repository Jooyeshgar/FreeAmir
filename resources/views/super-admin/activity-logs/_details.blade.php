<div class="space-y-3">
    @if ($activity['requestContext'])
        <dl class="grid gap-2 text-xs sm:grid-cols-2 sm:gap-3 xl:grid-cols-5">
            @foreach ($activity['requestContext'] as $context)
                <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-950/40">
                    <dt class="font-semibold text-slate-500">{{ $context['label'] }}</dt>
                    <dd class="mt-1 break-all font-mono" dir="ltr">{{ $context['value'] }}</dd>
                </div>
            @endforeach
        </dl>
    @endif
    @if ($activity['requestInput'])
        <pre class="max-h-72 overflow-auto rounded-xl bg-slate-950 p-3 text-xs text-slate-100" dir="ltr">{{ $activity['requestInput'] }}</pre>
    @endif
    @foreach ($activity['changes']->groupBy('model') as $model => $changes)
        <section x-data="{ modelOpen: false }" class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800">
            <header
                class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-800 dark:bg-slate-950/40">
                <span class="font-mono text-xs font-bold text-sky-700 dark:text-sky-300"
                    dir="ltr">{{ $model }}</span>
                <button type="button"
                    class="inline-flex items-center gap-1 rounded-lg bg-white px-2.5 py-1.5 text-[11px] font-semibold text-slate-600 ring-1 ring-inset ring-slate-200 transition hover:bg-slate-100 dark:bg-slate-900 dark:text-slate-300 dark:ring-slate-700 dark:hover:bg-slate-800"
                    @click="modelOpen = ! modelOpen" :aria-expanded="modelOpen" aria-label="{{ __('Details') }}">
                    <span x-text="modelOpen ? '{{ __('Close') }}' : '{{ __('Details') }}'"></span>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="1.8" class="h-3.5 w-3.5 transition-transform"
                        :class="modelOpen ? 'rotate-180' : ''" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                    </svg>
                </button>
            </header>
            <div x-cloak x-show="modelOpen" class="overflow-x-auto">
                <table class="table table-sm min-w-[32rem]">
                    <thead>
                        <tr>
                            <th>{{ __('Field') }}</th>
                            <th>{{ __('Previous value') }}</th>
                            <th>{{ __('New value') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($changes as $change)
                            <tr @class([
                                'bg-amber-50/70 font-bold dark:bg-amber-950/20' =>
                                    $change['old'] !== $change['new'],
                            ])>
                                <td class="font-mono text-xs" dir="ltr">{{ $change['field'] }}</td>
                                <td>
                                    <pre class="whitespace-pre-wrap break-all text-xs" dir="ltr">{{ $change['old'] }}</pre>
                                </td>
                                <td>
                                    <pre class="whitespace-pre-wrap break-all text-xs" dir="ltr">{{ $change['new'] }}</pre>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach
</div>
