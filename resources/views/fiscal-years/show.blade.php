<x-platform-layout :title="__('Fiscal year') . ' ' . $fiscalYear->year">
    <x-show-message-bags />
    <a href="{{ route('fiscal-years.index') }}" class="btn btn-ghost btn-sm mb-4">{{ __('Fiscal years') }}</a>
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <p class="text-sm text-slate-500">{{ __('Company') }}</p>
        <h1 class="mt-1 text-2xl font-bold">{{ $fiscalYear->company->name }} — {{ localizeNumber($fiscalYear->year) }}</h1>
        <div class="mt-5 grid gap-4 sm:grid-cols-3">
            <p>{{ __('Status') }}: <strong>{{ $fiscalYear->closed_at ? __('Closed') : __('Open') }}</strong></p>
            <p>{{ __('Users') }}: <strong>{{ localizeNumber($fiscalYear->users_count) }}</strong></p>
            <p>{{ __('Documents') }}: <strong>{{ localizeNumber($fiscalYear->documents_count) }}</strong></p>
        </div>
        <div class="mt-6 flex flex-wrap gap-2">
            @can('fiscal-years.edit')
                <a href="{{ route('fiscal-years.edit', $fiscalYear) }}" class="btn btn-primary">{{ __('Edit') }}</a>
            @endcan
            @can('companies.closing-wizard')
                <a href="{{ route('companies.closing-wizard', $fiscalYear) }}" class="btn btn-outline">{{ __('Review Fiscal Year Closing') }}</a>
            @endcan
            @can('fiscal-years.destroy')
                <form action="{{ route('fiscal-years.destroy', $fiscalYear) }}" method="POST" onsubmit="return confirm('{{ __('Are you sure you want to delete this fiscal year?') }}')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-error btn-outline">{{ __('Delete') }}</button>
                </form>
            @endcan
        </div>
    </section>
</x-platform-layout>
