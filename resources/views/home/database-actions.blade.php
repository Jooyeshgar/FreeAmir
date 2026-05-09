@hasrole('Super-Admin')
    @if ($isDebugMode && !$hasDocument && !session('hide_empty_database_demo_alert'))
        <div x-data="{ show: true }" x-show="show" x-transition class="alert alert-warning">
            <div class="flex items-center justify-between gap-4 w-full">
                <p class="m-0">{{ __('Your database tables are empty. Do you want to load demo data into your database?') }}</p>

                <div class="flex items-center gap-2">
                    <form method="POST" action="{{ route('home.seed-demo-data') }}" class="inline-block m-0">
                        @csrf
                        <button type="submit" class="btn btn-info btn-sm">{{ __('Seed Demo Data') }}</button>
                    </form>

                    <form x-ref="hideForm" method="POST" action="{{ route('home.hide-demo-alert') }}" target="hidden_iframe" class="inline-block m-0">
                        @csrf
                        <button type="submit" class="btn btn-error btn-sm"
                            @click.prevent="
                                show = false;
                                $nextTick(() => $refs.hideForm.submit());
                            "
                        >
                            {{ __('Need not') }}
                        </button>
                    </form>
                </div>
            </div>

            <iframe name="hidden_iframe" class="hidden"></iframe>
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
