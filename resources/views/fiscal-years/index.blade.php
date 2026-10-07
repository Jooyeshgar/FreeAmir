<x-platform-layout :title="__('Fiscal years')">
    <x-show-message-bags />

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">{{ __('Fiscal years') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('Companies and fiscal years') }}</p>
        </div>
        @can('fiscal-years.create')
            <a href="{{ route('fiscal-years.create') }}" class="btn btn-primary rounded-xl">+ {{ __('Create Fiscal Year') }}</a>
        @endcan
    </div>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <form action="{{ route('fiscal-years.index') }}" method="GET" class="flex flex-wrap items-center gap-2 border-b border-slate-200 p-4 dark:border-slate-800">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="{{ __('Search companies') }}" class="input input-bordered w-64 max-w-full">
            <select name="status" class="select select-bordered" aria-label="{{ __('Status') }}">
                <option value="">{{ __('All statuses') }}</option>
                <option value="open" @selected(request('status') === 'open')>{{ __('Open') }}</option>
                <option value="closed" @selected(request('status') === 'closed')>{{ __('Closed') }}</option>
            </select>
            <button type="submit" class="btn btn-neutral">{{ __('Filter') }}</button>
            @if (request()->hasAny(['search', 'status']))
                <a href="{{ route('fiscal-years.index') }}" class="btn btn-ghost">{{ __('Clear') }}</a>
            @endif
        </form>
        <div class="overflow-x-auto">
            <table class="table table-lg">
                <thead class="bg-slate-50 dark:bg-slate-950/30">
                    <tr>
                        <th>{{ __('Company') }}</th>
                        <th>{{ __('Fiscal year') }}</th>
                        <th>{{ __('Users') }}</th>
                        <th>{{ __('Documents') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($years as $year)
                        <tr>
                            <td class="font-semibold">{{ $year->company->name }}</td>
                            <td>
                                @can('fiscal-years.show')
                                    <a href="{{ route('fiscal-years.show', $year) }}" class="font-mono text-primary hover:underline">{{ localizeNumber($year->year) }}</a>
                                @else
                                    {{ localizeNumber($year->year) }}
                                @endcan
                            </td>
                            <td>{{ localizeNumber($year->users_count) }}</td>
                            <td>{{ localizeNumber($year->documents_count) }}</td>
                            <td><span @class(['badge', 'badge-success badge-outline' => !$year->closed_at, 'badge-ghost' => $year->closed_at])>{{ $year->closed_at ? __('Closed') : __('Open') }}</span></td>
                            <td class="text-end">
                                <div class="inline-flex items-center gap-2">
                                    @can('fiscal-years.edit')
                                        <a href="{{ route('fiscal-years.edit', $year) }}" class="btn btn-ghost btn-sm">{{ __('Edit') }}</a>
                                    @endcan
                                    @can('fiscal-years.destroy')
                                        <form action="{{ route('fiscal-years.destroy', $year) }}" method="POST" onsubmit="return confirm('{{ __('Are you sure you want to delete this fiscal year?') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-ghost btn-sm text-error">{{ __('Delete') }}</button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-16 text-center text-slate-500">{{ __('No fiscal years found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($years->hasPages())
            <div class="border-t border-slate-200 p-4 dark:border-slate-800">{{ $years->links() }}</div>
        @endif
    </section>
</x-platform-layout>
