@can('reports.company-overview.seed-demo-data')
    @if ($isDebugMode && !$hasDocument)
        <div class="alert alert-warning">
            <p>{{ __('Your database tables are empty. Do you want to load demo data into your database?') }}</p>
            <form method="POST" action="{{ route('reports.company-overview.seed-demo-data') }}">
                @csrf
                <button type="submit" class="btn btn-info text-xs h-14 md:h-8">{{ __('Seed Demo Data') }}</button>
            </form>
        </div>
    @endif
@endcan

@can('reports.company-overview.refresh-database')
    @if ($isDebugMode)
        <div role="alert" class="alert alert-warning flex flex-col mt-4 mb-4">
            <div class="w-full flex items-center gap-2">
                <p>{{ __('Do you want to refresh your database?') }}</p>
                <form method="POST" action="{{ route('reports.company-overview.refresh-database') }}"
                    onsubmit="return confirm('{{ __('Are you sure you want to refresh the database? This will delete all current tables and data and rebuild the database with demo data.') }}')">
                    @csrf
                    <button type="submit" class="btn btn-error btn-sm text-xs">{{ __('Refresh Database') }}</button>
                </form>
            </div>
        </div>
    @endif
@endcan
