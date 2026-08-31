<x-app-layout :title="__('Home')">
    <x-show-message-bags />

    <main class="home-dashboard w-full px-2 pb-6 text-sm sm:px-4 sm:text-base md:px-5 lg:px-6">
        @include('home._header', compact('homeVariant'))

        @include('home._financial-values')

        @include('home._access-sections')
    </main>

    @push('scripts')
        <script>
            window.privateHomeMetric = function (url) {
                return {
                    revealed: false,
                    loaded: false,
                    loading: false,
                    error: false,
                    formattedValue: '',
                    unit: '',

                    async toggle() {
                        if (this.revealed) {
                            this.revealed = false;
                            return;
                        }

                        this.revealed = true;
                        if (!this.loaded && !this.loading) {
                            await this.load();
                        }
                    },

                    async load() {
                        this.loading = true;
                        this.error = false;

                        try {
                            const response = await fetch(url, {
                                cache: 'no-store',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                            });

                            if (!response.ok) {
                                throw new Error(`Metric request failed with status ${response.status}`);
                            }

                            const data = await response.json();
                            this.formattedValue = data.formattedValue;
                            this.unit = data.unit;
                            this.loaded = true;
                        } catch (error) {
                            this.error = true;
                        } finally {
                            this.loading = false;
                        }
                    },

                    async retry() {
                        await this.load();
                    },

                    reset() {
                        this.revealed = false;
                        this.loaded = false;
                        this.loading = false;
                        this.error = false;
                        this.formattedValue = '';
                        this.unit = '';
                    },
                };
            };

            window.addEventListener('pageshow', (event) => {
                if (event.persisted) {
                    window.dispatchEvent(new CustomEvent('home-private-reset'));
                }
            });
        </script>
    @endpush
</x-app-layout>
