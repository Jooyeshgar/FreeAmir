<div class="space-y-3">
    @if ($activity['requestContext'])
        <dl class="grid gap-2 text-xs sm:grid-cols-2 sm:gap-3 xl:grid-cols-5">
            @foreach ($activity['requestContext'] as $context)
                <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-950/40"><dt class="font-semibold text-slate-500">{{ $context['label'] }}</dt><dd class="mt-1 break-all font-mono" dir="ltr">{{ $context['value'] }}</dd></div>
            @endforeach
        </dl>
    @endif
    @if ($activity['requestInput'])
        <pre class="max-h-72 overflow-auto rounded-xl bg-slate-950 p-3 text-xs text-slate-100" dir="ltr">{{ $activity['requestInput'] }}</pre>
    @endif
    @foreach ($activity['changes']->groupBy('model') as $model => $changes)
        <section class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800">
            <header class="border-b border-slate-200 bg-slate-50 px-3 py-2 font-mono text-xs font-bold dark:border-slate-800 dark:bg-slate-950/40" dir="ltr">{{ $model }}</header>
            <table class="table table-sm"><thead><tr><th>{{ __('Field') }}</th><th>{{ __('Previous value') }}</th><th>{{ __('New value') }}</th></tr></thead><tbody>
                @foreach ($changes as $change)<tr><td dir="ltr">{{ $change['field'] }}</td><td><pre dir="ltr">{{ $change['old'] }}</pre></td><td><pre dir="ltr">{{ $change['new'] }}</pre></td></tr>@endforeach
            </tbody></table>
        </section>
    @endforeach
</div>
