<header class="flex min-w-0 items-center justify-between gap-3 bg-gray-200 px-3 py-2 sm:px-4">
    <div class="flex min-w-0 items-center gap-2">
        <img src="/images/logo.png" alt="Logo" class="h-10 w-10 shrink-0 sm:h-12 sm:w-12">
        <h1 class="text-sm font-bold leading-tight sm:text-base">{{ __('Amirs free accounting software') }}</h1>
    </div>
    <div class="language-select">
        <form method="POST" action="{{ route('locale') }}" class="language-picker__form">
            @csrf
            <label for="auth-locale" class="sr-only">{{ __('Language') }}</label>
            <select id="auth-locale" name="locale" class="locale select select-sm pr-10 pl-3 py-2 max-w-28 sm:select-md sm:max-w-none" onchange="this.form.submit()">
                <option lang="fa" value="fa" @selected(app()->isLocale('fa'))>{{ __('Farsi') }}</option>
                <option lang="en" value="en" @selected(app()->isLocale('en'))>{{ __('English') }}</option>
            </select>
        </form>
    </div>
</header>