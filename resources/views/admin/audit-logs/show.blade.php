<x-app-layout :title="__('Audit Event #:id', ['id' => $auditLog->id])">
    <div class="grid gap-5 xl:grid-cols-3">
        <section class="rounded-2xl border border-base-300 bg-base-100 p-5 shadow-sm xl:col-span-2">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-base-300 pb-4">
                <div><p class="text-xs uppercase tracking-wider text-base-content/45">{{ __('Action') }}</p><h2 class="mt-1 font-mono text-lg font-bold">{{ $auditLog->action }}</h2></div>
            </div>
            <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                @foreach ([
                    __('Actor') => $auditLog->actor?->name ?? __('System'),
                    __('Company') => $auditLog->company?->name ?? '—',
                    __('Method') => $auditLog->method,
                    __('Route') => $auditLog->route_name ?? '—',
                    __('URL') => $auditLog->url,
                    __('IP address') => $auditLog->ip_address ?? '—',
                    __('Occurred at') => formatDateTime($auditLog->created_at),
                    __('Subject') => $auditLog->subject_type ? class_basename($auditLog->subject_type).' #'.$auditLog->subject_id : '—',
                ] as $label => $value)
                    <div><dt class="text-xs font-semibold text-base-content/45">{{ $label }}</dt><dd class="mt-1 break-all text-sm font-medium">{{ $value }}</dd></div>
                @endforeach
            </dl>
        </section>
        <aside class="rounded-2xl border border-base-300 bg-base-100 p-5 shadow-sm">
            <h2 class="font-bold">{{ __('Request context') }}</h2>
            <pre class="mt-4 max-h-[32rem] overflow-auto rounded-xl bg-neutral p-4 text-xs leading-6 text-neutral-content" dir="ltr"><code>{{ json_encode($auditLog->request_data ?? new stdClass, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</code></pre>
            <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-outline btn-sm mt-4 w-full">{{ __('Back to audit trail') }}</a>
        </aside>
    </div>
</x-app-layout>
