@hasrole('Super-Admin')
    @if ($isDebugMode && !$hasDocument)
        <div class="alert alert-warning">
            <p>{{ __('Your database tables are empty. Do you want to load demo data into your database?') }}</p>
            <form method="POST" action="{{ route('home.seed-demo-data') }}" class="inline-block m-0">
                @csrf
                <button type="submit" class="btn btn-info">{{ __('Seed Demo Data') }}</button>
            </form>
        </div>
    @endif

    @if ($isDebugMode)
        <div role="alert" class="alert alert-warning flex flex-col mt-4 mb-4">
            <div class="w-full flex items-center gap-2">
                <p class="m-0">{{ __('Do you want to refresh your database?') }}</p>
                <form method="POST" action="{{ route('home.refresh-database') }}" class="inline-block m-0"
                    onsubmit="return confirm('{{ __('Are you sure you want to refresh the database? This will delete all current tables and data and rebuild the database with demo data.') }}')">
                    @csrf
                    <button type="submit" class="btn btn-error btn-sm">{{ __('Refresh Database') }}</button>
                </form>
            </div>
            <p class="text-sm opacity-80 w-full">
                {{ __('To disable database refresh, set :APP_DEBUG=:false in the :env file.', ['env' => '.env', 'APP_DEBUG' => 'APP_DEBUG', 'false' => 'false']) }}
            </p>
        </div>
    @endif
@endhasrole
