<x-app-layout :title="__('about.about_freeamir')">

    <div class="mx-auto max-w-4xl space-y-4 px-4 py-6">

        {{-- Page Header --}}
        <div>
            <h1 class="text-2xl font-bold text-base-content">{{ __('about.about_freeamir') }}</h1>
            <p class="mt-1 text-sm text-base-content/60">{{ __('about.description') }}</p>
        </div>

        <x-show-message-bags />

        {{-- Branding & Version --}}
        <section class="card border border-base-300 bg-base-100/90 shadow-sm">
            <div class="card-body flex flex-col items-center gap-3 sm:flex-row sm:items-center sm:gap-5">
                <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                    <img src="/images/logo.png" alt="FreeAmir" class="h-10 w-10 object-contain">
                </div>
                <div class="text-center sm:text-start">
                    <h2 class="text-xl font-bold">FreeAmir</h2>
                    <div class="mt-1 flex flex-wrap items-center justify-center gap-2 sm:justify-start">
                        <span class="badge badge-primary badge-sm font-mono">{{ __('about.version') }}: {{ $version }}</span>
                        <span @class([
                            'badge badge-sm',
                            'badge-warning' => $appEnv === 'local',
                            'badge-info' => $appEnv === 'staging',
                            'badge-success' => $appEnv === 'production',
                        ])>{{ $gcSettings['app_env']['options'][$appEnv] ?? $appEnv }}</span>
                        @can('update-global-configs')
                            <button type="button" class="btn btn-ghost btn-xs btn-square" aria-label="{{ __('Edit :setting', ['setting' => $gcSettings['app_env']['title']]) }}" onclick="gc_modal_app_env.showModal()">
                                <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>
                        @endcan
                    </div>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">

            {{-- System Information --}}
            <section class="card border border-base-300 bg-base-100/90 shadow-sm">
                <div class="card-body p-4">
                    <h2 class="flex items-center gap-2 text-base font-semibold">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        {{ __('about.system_information') }}
                    </h2>
                    <div class="divider my-0"></div>
                    <table class="table table-sm">
                        <tbody>
                            <tr>
                                <td class="font-medium opacity-70">{{ __('about.debug_mode') }}</td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        @if($debugMode)
                                            <span class="badge badge-warning badge-sm">{{ __('about.enabled') }}</span>
                                        @else
                                            <span class="badge badge-success badge-sm">{{ __('about.disabled') }}</span>
                                        @endif
                                        @can('update-global-configs')
                                            <button type="button" class="btn btn-ghost btn-xs btn-square" aria-label="{{ __('Edit :setting', ['setting' => $gcSettings['app_debug']['title']]) }}" onclick="gc_modal_app_debug.showModal()">
                                                <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="font-medium opacity-70">{{ __('about.database') }}</td>
                                <td>
                                    @if($dbConnected)
                                        <span class="badge badge-success badge-sm gap-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                            {{ __('about.connected') }}
                                        </span>
                                    @else
                                        <span class="badge badge-error badge-sm gap-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                            {{ __('about.disconnected') }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="font-medium opacity-70">{{ __('about.database_driver') }}</td>
                                <td><span class="font-mono text-sm">{{ $dbDriver }}</span></td>
                            </tr>
                            <tr>
                                <td class="font-medium opacity-70">{{ __('about.php_version') }}</td>
                                <td><span class="font-mono text-sm">{{ $phpVersion }}</span></td>
                            </tr>
                            <tr>
                                <td class="font-medium opacity-70">{{ __('about.laravel_version') }}</td>
                                <td><span class="font-mono text-sm">{{ $laravelVersion }}</span></td>
                            </tr>
                            <tr>
                                <td class="font-medium opacity-70">{{ __('about.locale') }}</td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-sm">{{ $gcSettings['app_locale']['options'][$locale] ?? $locale }}</span>
                                        @can('update-global-configs')
                                            <button type="button" class="btn btn-ghost btn-xs btn-square" aria-label="{{ __('Edit :setting', ['setting' => $gcSettings['app_locale']['title']]) }}" onclick="gc_modal_app_locale.showModal()">
                                                <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="font-medium opacity-70">{{ __('about.timezone') }}</td>
                                <td><span class="font-mono text-sm">{{ $timezone }}</span></td>
                            </tr>
                            <tr>
                                <td class="font-medium opacity-70">{{ __('about.server_os') }}</td>
                                <td><span class="font-mono text-sm">{{ $serverOs }}</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- License & Developer --}}
            <section class="card border border-base-300 bg-base-100/90 shadow-sm">
                <div class="card-body p-4">
                    <h2 class="flex items-center gap-2 text-base font-semibold">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                        </svg>
                        {{ __('about.license') }}
                    </h2>
                    <div class="divider my-0"></div>
                    <div class="rounded-lg bg-base-200/60 p-3 text-sm leading-relaxed">
                        <div class="mb-1 font-bold">GNU General Public License v3.0</div>
                        <p class="opacity-80">{{ __('about.license_text') }}</p>
                    </div>

                    <div class="divider my-1"></div>

                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                            </svg>
                        </div>
                        <div>
                            <div class="text-xs opacity-60">{{ __('about.developed_by') }}</div>
                            <div class="font-semibold text-sm">{{ __('about.developer_name') }}</div>
                        </div>
                    </div>

                    <div class="divider my-1"></div>

                    {{-- Support Links --}}
                    <h3 class="flex items-center gap-2 text-sm font-semibold">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        {{ __('about.support') }}
                    </h3>
                    <div class="flex flex-wrap gap-2">
                        <a href="https://github.com/Jooyeshgar/FreeAmir" target="_blank" rel="noopener noreferrer"
                            class="btn btn-outline btn-sm gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                            </svg>
                            {{ __('about.source_code') }}
                        </a>
                        <a href="https://github.com/Jooyeshgar/FreeAmir/issues" target="_blank" rel="noopener noreferrer"
                            class="btn btn-outline btn-sm gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            {{ __('about.report_issue') }}
                        </a>
                    </div>
                </div>
            </section>

        </div>

    </div>

    @can('update-global-configs')
        @foreach ($gcSettings as $key => $setting)
            <dialog id="gc_modal_{{ $key }}" class="modal">
                <div class="modal-box">
                    <h3 class="text-lg font-bold">{{ __('Edit :setting', ['setting' => $setting['title']]) }}</h3>

                    <div role="alert" class="alert alert-warning mt-3 text-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <span>{{ __('If you set a value here, it is no longer read from the .env file and editing .env will have no effect. To read it from the .env file again, choose Default.') }}</span>
                    </div>

                    <form method="POST" action="{{ route('update-global-configs') }}" class="mt-4 space-y-3">
                        @csrf
                        @method('PUT')

                        <label class="form-control w-full">
                            <span class="label-text text-sm">{{ $setting['title'] }}</span>
                            <select name="{{ $key }}" class="select select-bordered w-full">
                                <option value="" @selected($setting['current'] === null)>{{ __('Default') }}</option>
                                @foreach ($setting['options'] as $value => $optLabel)
                                    <option value="{{ $value }}" @selected($setting['current'] === $value)>{{ $optLabel }}</option>
                                @endforeach
                            </select>
                        </label>

                        <div class="modal-action">
                            <button type="button" class="btn" onclick="gc_modal_{{ $key }}.close()">{{ __('Close') }}</button>
                            <button type="submit" class="btn btn-primary">{{ __('save') }}</button>
                        </div>
                    </form>
                </div>
            </dialog>
        @endforeach
    @endcan

</x-app-layout>
