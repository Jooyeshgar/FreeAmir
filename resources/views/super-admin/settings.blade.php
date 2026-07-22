<x-super-admin-layout :title="__('Application settings')">
    <x-show-message-bags />

    <section class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-600 dark:text-emerald-400">{{ __('System') }}</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight">{{ __('Application settings') }}</h1>
            <p class="mt-2 max-w-2xl text-sm text-slate-500 dark:text-slate-400">{{ __('Configure platform-wide behavior and review the application environment.') }}</p>
        </div>
        <span @class([
            'badge gap-2 px-3 py-3',
            'badge-success badge-outline' => $dbConnected,
            'badge-error badge-outline' => ! $dbConnected,
        ])>
            <span @class(['h-2 w-2 rounded-full', 'bg-success' => $dbConnected, 'bg-error' => ! $dbConnected])></span>
            {{ $dbConnected ? __('Database connected') : __('Database disconnected') }}
        </span>
    </section>

    <form method="POST" action="{{ route('update-global-configs') }}" class="space-y-6">
        @csrf
        @method('PUT')

        <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <header class="border-b border-slate-200/80 px-5 py-4 dark:border-slate-800">
                <h2 class="font-bold">{{ __('Global configuration') }}</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('These settings affect every user and company on the platform.') }}</p>
            </header>

            @php
                $descriptions = [
                    'app_env' => __('Controls the runtime environment used by the application.'),
                    'app_debug' => __('Show detailed application errors. Keep this disabled in production.'),
                    'app_locale' => __('Sets the default language and number presentation.'),
                    'app_registration' => __('Allow visitors to create new accounts.'),
                    'app_email_verification' => __('Require new accounts to verify their email address.'),
                ];
            @endphp

            <div class="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($gcSettings as $key => $setting)
                    <label class="rounded-xl border border-slate-200 bg-slate-50/70 p-4 transition-colors focus-within:border-emerald-400 dark:border-slate-800 dark:bg-slate-950/30">
                        <span class="block text-sm font-semibold">{{ $setting['title'] }}</span>
                        <span class="mt-1 block min-h-10 text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $descriptions[$key] }}</span>
                        <select name="{{ $key }}" class="select select-bordered mt-3 w-full bg-white dark:bg-slate-900">
                            <option value="">{{ __('Use environment default') }}</option>
                            @foreach ($setting['options'] as $value => $optionLabel)
                                <option value="{{ $value }}" @selected(old($key, $setting['current']) === $value)>{{ $optionLabel }}</option>
                            @endforeach
                        </select>
                    </label>
                @endforeach
            </div>

            <div class="flex flex-col justify-between gap-3 border-t border-slate-200/80 bg-slate-50/60 px-5 py-4 dark:border-slate-800 dark:bg-slate-950/20 sm:flex-row sm:items-center">
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Saved values override the corresponding environment configuration.') }}</p>
                <button type="submit" class="btn btn-primary min-w-40">{{ __('Save application settings') }}</button>
            </div>
        </section>
    </form>

    <section class="mt-6 overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <header class="border-b border-slate-200/80 px-5 py-4 dark:border-slate-800">
            <h2 class="font-bold">{{ __('Runtime information') }}</h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Read-only details about this installation.') }}</p>
        </header>
        <dl class="grid md:grid-cols-2 xl:grid-cols-4">
            <div class="border-b border-slate-100 p-5 dark:border-slate-800 xl:border-e"><dt class="text-xs text-slate-500">{{ __('Application version') }}</dt><dd class="mt-2 font-mono text-sm font-semibold">{{ localizeNumber($version) }}</dd></div>
            <div class="border-b border-slate-100 p-5 dark:border-slate-800 xl:border-e"><dt class="text-xs text-slate-500">{{ __('PHP version') }}</dt><dd class="mt-2 font-mono text-sm font-semibold">{{ localizeNumber($phpVersion) }}</dd></div>
            <div class="border-b border-slate-100 p-5 dark:border-slate-800 xl:border-e"><dt class="text-xs text-slate-500">{{ __('Laravel version') }}</dt><dd class="mt-2 font-mono text-sm font-semibold">{{ localizeNumber($laravelVersion) }}</dd></div>
            <div class="border-b border-slate-100 p-5 dark:border-slate-800"><dt class="text-xs text-slate-500">{{ __('Database driver') }}</dt><dd class="mt-2 font-mono text-sm font-semibold">{{ $dbDriver }}</dd></div>
            <div class="p-5 xl:border-e xl:border-slate-100 dark:xl:border-slate-800"><dt class="text-xs text-slate-500">{{ __('Environment') }}</dt><dd class="mt-2 text-sm font-semibold">{{ $gcSettings['app_env']['options'][$appEnv] ?? $appEnv }}</dd></div>
            <div class="p-5 xl:border-e xl:border-slate-100 dark:xl:border-slate-800"><dt class="text-xs text-slate-500">{{ __('Locale') }}</dt><dd class="mt-2 text-sm font-semibold">{{ $gcSettings['app_locale']['options'][$locale] ?? $locale }}</dd></div>
            <div class="p-5 xl:border-e xl:border-slate-100 dark:xl:border-slate-800"><dt class="text-xs text-slate-500">{{ __('Timezone') }}</dt><dd class="mt-2 font-mono text-sm font-semibold">{{ localizeNumber($timezone) }}</dd></div>
            <div class="p-5"><dt class="text-xs text-slate-500">{{ __('Server operating system') }}</dt><dd class="mt-2 font-mono text-sm font-semibold">{{ localizeNumber($serverOs) }}</dd></div>
        </dl>
    </section>
</x-super-admin-layout>
