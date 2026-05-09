@hasrole('Super-Admin')
    @if ($isDebugMode && !$hasDocument && !session('hide_empty_database_demo_alert'))
        <div class="alert alert-warning"
            x-data="{
                loading: false,
                    visible: true,
                    async dismiss() {
                        if (this.loading) return;
                        this.loading = true;
                        this.visible = false;
            
                        try {
                            const res = await fetch('{{ route('home.hide-demo-alert') }}', {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'Accept': 'application/json'
                                },
                                credentials: 'same-origin'
                            });
            
                            if (!res.ok) {
                                this.visible = true;
                                const t = await res.text();
                            }
                        } catch (e) {
                            this.visible = true;
                        } finally {
                            this.loading = false;
                        }
                    }
                }"
            x-show="visible"
            x-transition
        >
            <div class="flex items-center justify-between gap-4 w-full">
                <p class="m-0">{{ __('Your database tables are empty. Do you want to load demo data into your database?') }}</p>

                <div class="flex items-center gap-2">
                    <form method="POST" action="{{ route('home.seed-demo-data') }}" class="inline-block m-0">
                        @csrf
                        <button type="submit" class="btn btn-info btn-sm">{{ __('Seed Demo Data') }}</button>
                    </form>

                    <button type="button" class="btn btn-error btn-sm" @click="dismiss" :disabled="loading">{{ __('Dismiss') }}</button>
                </div>
            </div>
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
